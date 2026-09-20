<?php

namespace App\Http\Requests;

use App\Filament\Pages\ExportSessionsPage;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExportSessionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * `bail` is hier dragend: zonder die regel draait `exists` alsnog met een
     * niet-numerieke waarde, en dan geeft PostgreSQL een harde fout op de bigint-kolom.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['bail', 'required', 'date'],
            'to' => ['bail', 'required', 'date', 'after_or_equal:from'],
            'format' => ['nullable', 'string', 'in:csv,pdf'],
            'weapon_ids' => ['nullable', 'array', 'max:50'],
            'weapon_ids.*' => [
                'bail',
                'integer',
                Rule::exists('weapons', 'id')->where('user_id', $this->user()?->getKey()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.required' => 'Kies een startdatum voor de export.',
            'from.date' => 'De startdatum is geen geldige datum.',
            'to.required' => 'Kies een einddatum voor de export.',
            'to.date' => 'De einddatum is geen geldige datum.',
            'to.after_or_equal' => 'De einddatum moet op of na de startdatum liggen.',
            'format.in' => 'Kies csv of pdf als formaat.',
            'weapon_ids.array' => 'Geef het wapenfilter op als een lijst wapennummers.',
            'weapon_ids.max' => 'Kies maximaal 50 wapens in het filter.',
            'weapon_ids.*.integer' => 'Het wapenfilter mag alleen wapennummers bevatten.',
            'weapon_ids.*.exists' => 'Een van de gekozen wapens bestaat niet of is niet van jou.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'from' => 'startdatum',
            'to' => 'einddatum',
            'format' => 'formaat',
            'weapon_ids' => 'wapenfilter',
        ];
    }

    public function periodFrom(): Carbon
    {
        return Carbon::parse($this->validated('from'));
    }

    public function periodTo(): Carbon
    {
        return Carbon::parse($this->validated('to'));
    }

    /**
     * @return array<int, int>|null
     */
    public function weaponIds(): ?array
    {
        $weaponIds = $this->validated('weapon_ids');

        return filled($weaponIds)
            ? array_map(intval(...), $weaponIds)
            : null;
    }

    public function format(): string
    {
        return $this->validated('format') ?? 'csv';
    }

    /**
     * De exportpagina stuurt het wapenfilter als komma-string mee in de querystring,
     * terwijl de validatie per wapennummer werkt.
     */
    protected function prepareForValidation(): void
    {
        $weaponIds = $this->input('weapon_ids');

        if (is_string($weaponIds)) {
            $weaponIds = array_values(array_filter(
                array_map(trim(...), explode(',', $weaponIds)),
                fn (string $weaponId): bool => $weaponId !== '',
            ));
        }

        $this->merge([
            'weapon_ids' => blank($weaponIds) ? null : $weaponIds,
        ]);
    }

    protected function failedValidation(Validator $validator): void
    {
        Notification::make()
            ->title('Export niet gestart')
            ->body(implode(' ', $validator->errors()->all()))
            ->danger()
            ->send();

        throw (new ValidationException($validator))
            ->redirectTo(ExportSessionsPage::getUrl());
    }
}
