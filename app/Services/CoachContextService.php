<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Session;
use App\Models\SessionWeapon;
use App\Models\User;
use App\Models\Weapon;
use EslamRedaDiv\FilamentCopilot\Models\CopilotConversation;
use Illuminate\Support\Collection;

/**
 * Levert context-data voor de AI-coach pagina: recente Copilot-gesprekken,
 * laatste sessie en meest-gebruikte wapen van deze user.
 *
 * Hybride per decision 4: Copilot regelt message-state, deze service
 * voedt de chat-list rail + context-rail in de eigen 3-koloms view.
 */
final class CoachContextService
{
    public function __construct(private readonly User $user) {}

    /**
     * Recente Copilot-gesprekken van deze user (laatst bewerkt eerst).
     */
    public function recentConversations(int $limit = 6): Collection
    {
        return CopilotConversation::query()
            ->forParticipant($this->user)
            ->latest('updated_at')
            ->limit($limit)
            ->get();
    }

    public function latestConversation(): ?CopilotConversation
    {
        return $this->recentConversations(1)->first();
    }

    public function lastSession(): ?Session
    {
        return Session::query()
            ->where('user_id', $this->user->getKey())
            ->with(['sessionWeapons.weapon', 'aiReflection'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Wapen met het hoogste aantal sessies voor deze user.
     */
    public function topWeapon(): ?Weapon
    {
        $weaponId = SessionWeapon::query()
            ->whereIn('session_id', Session::query()
                ->where('user_id', $this->user->getKey())
                ->select('id'))
            ->selectRaw('weapon_id, COUNT(DISTINCT session_id) as session_count')
            ->groupBy('weapon_id')
            ->orderByDesc('session_count')
            ->value('weapon_id');

        return $weaponId
            ? Weapon::query()->where('user_id', $this->user->getKey())->find($weaponId)
            : null;
    }
}
