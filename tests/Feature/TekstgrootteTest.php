<?php

use Symfony\Component\Finder\Finder;

/**
 * Bestanden waarin een font-size gezet kan worden.
 *
 * @return list<array{string, string}>
 */
function tekstgrootteBronnen(): array
{
    $finder = (new Finder)
        ->files()
        ->in([resource_path('views'), resource_path('css'), app_path()])
        ->name(['*.php', '*.css']);

    $bronnen = [];
    foreach ($finder as $bestand) {
        $bronnen[] = [str_replace(base_path().'/', '', $bestand->getRealPath()), $bestand->getContents()];
    }

    return $bronnen;
}

/**
 * Bestanden die nog px gebruiken en in een vervolg omgezet worden (conflict met lopend werk aan het schotenbord).
 *
 * @var list<string>
 */
const TEKSTGROOTTE_UITZONDERINGEN = ['resources/views/livewire/session-shot-board.blade.php'];

it('gebruikt geen font-size in px, zodat de browserinstelling meeschaalt', function (): void {
    $overtredingen = [];

    foreach (tekstgrootteBronnen() as [$pad, $inhoud]) {
        if (in_array($pad, TEKSTGROOTTE_UITZONDERINGEN, true)) {
            continue;
        }
        if (preg_match_all('/font-size:\s*(?!clamp\()[0-9.]+px/', $inhoud, $treffers)) {
            $overtredingen[] = $pad.': '.implode(', ', $treffers[0]);
        }
    }

    expect($overtredingen)->toBe([]);
});

it('laat geen font-size onder de ondergrens van 12px (0.75rem) toe', function (): void {
    $overtredingen = [];

    foreach (tekstgrootteBronnen() as [$pad, $inhoud]) {
        if (in_array($pad, TEKSTGROOTTE_UITZONDERINGEN, true)) {
            continue;
        }
        preg_match_all('/font-size:\s*([0-9.]+)(px|rem)/', $inhoud, $treffers, PREG_SET_ORDER);
        foreach ($treffers as $treffer) {
            $px = $treffer[2] === 'rem' ? (float) $treffer[1] * 16 : (float) $treffer[1];
            if ($px < 12) {
                $overtredingen[] = $pad.': '.$treffer[0];
            }
        }
    }

    expect($overtredingen)->toBe([]);
});

it('zet de typografietokens in rem met body op minimaal 1rem', function (): void {
    $css = file_get_contents(resource_path('css/aimtrack-tokens.css'));

    foreach (['heading', 'subheading', 'body', 'small', 'micro'] as $naam) {
        expect($css)->toMatch('/--at-text-'.$naam.':\s*[0-9.]+rem;/');
    }
    expect($css)->toMatch('/--at-label:\s*[0-9.]+rem;/');
    expect($css)->toMatch('/--at-text-body:\s*1rem;/');
    expect($css)->toMatch('/--at-text-small:\s*0\.875rem;/');
    expect($css)->toMatch('/--at-text-micro:\s*0\.75rem;/');
});

it('gebruikt voor .at-label een bescheiden letterafstand', function (): void {
    $css = file_get_contents(resource_path('css/aimtrack-tokens.css'));

    preg_match('/\.at-label\s*\{[^}]*letter-spacing:\s*([0-9.]+)em/', $css, $m);

    expect((float) $m[1])->toBeLessThanOrEqual(0.12);
});
