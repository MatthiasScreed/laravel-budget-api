<?php

namespace App\Services;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Str;

class ReferralService
{
    public const REFERRER_XP      = 200;
    public const REFERRED_FREEZES = 1;
    public const MAX_REFERRALS    = 50; // Sécurité anti-abus

    /**
     * Générer un code parrain unique pour un utilisateur
     */
    public function generateCode(User $user): string
    {
        if ($user->referral_code) {
            return $user->referral_code;
        }

        $code = $this->makeUniqueCode();
        $user->update(['referral_code' => $code]);

        return $code;
    }

    /**
     * Valider qu'un code parrain existe et est utilisable
     */
    public function validateCode(string $code): ?User
    {
        $referrer = User::where('referral_code', strtoupper($code))
            ->whereNull('deleted_at')
            ->first();

        if (!$referrer) {
            return null;
        }

        // Anti-abus : max 50 parrainages
        if ($referrer->referral_count >= self::MAX_REFERRALS) {
            return null;
        }

        return $referrer;
    }

    /**
     * Enregistrer un parrainage après inscription du filleul
     */
    public function applyReferral(User $newUser, string $code): bool
    {
        $referrer = $this->validateCode($code);

        if (!$referrer || $referrer->id === $newUser->id) {
            return false;
        }

        // Vérifier que le filleul n'a pas déjà été parrainé
        $alreadyReferred = Referral::where('referred_id', $newUser->id)->exists();
        if ($alreadyReferred) {
            return false;
        }

        // Créer le parrainage
        $referral = Referral::create([
            'referrer_id'      => $referrer->id,
            'referred_id'      => $newUser->id,
            'referral_code'    => strtoupper($code),
            'status'           => Referral::STATUS_COMPLETED,
            'referrer_xp'      => self::REFERRER_XP,
            'referred_freezes' => self::REFERRED_FREEZES,
            'completed_at'     => now(),
        ]);

        // Récompenser les deux parties
        $this->rewardReferrer($referrer, $referral);
        $this->rewardReferred($newUser, $referral);

        return true;
    }

    /**
     * Obtenir les stats de parrainage d'un utilisateur
     */
    public function getStats(User $user): array
    {
        $code      = $this->generateCode($user);
        $referrals = Referral::where('referrer_id', $user->id)->get();

        return [
            'referral_code'     => $code,
            'referral_url'      => $this->buildUrl($code),
            'total_referrals'   => $referrals->count(),
            'completed'         => $referrals->where('status', '!=', Referral::STATUS_PENDING)->count(),
            'total_xp_earned'   => $referrals->where('status', Referral::STATUS_REWARDED)->sum('referrer_xp'),
            'next_reward'       => $this->getNextReward($referrals->count()),
            'recent_referrals'  => $referrals->sortByDesc('created_at')->take(5)->map(fn ($r) => [
                'name'         => $r->referred?->name ?? 'En attente',
                'completed_at' => $r->completed_at?->diffForHumans(),
                'status'       => $r->status,
            ])->values(),
        ];
    }

    // ==========================================
    // HELPERS PRIVÉS
    // ==========================================

    private function rewardReferrer(User $referrer, Referral $referral): void
    {
        // +200 XP
        $referrer->addXp($referral->referrer_xp);

        // Incrémenter le compteur
        $referrer->increment('referral_count');

        // Bonus freeze à 5 parrainages
        if ($referrer->fresh()->referral_count % 5 === 0) {
            app(StreakService::class)->awardFreeze($referrer, 'referral_milestone');
        }

        $referral->update([
            'status'      => Referral::STATUS_REWARDED,
            'rewarded_at' => now(),
        ]);

        \Log::info('Parrain récompensé', [
            'referrer_id' => $referrer->id,
            'xp'          => $referral->referrer_xp,
            'total'       => $referrer->fresh()->referral_count,
        ]);
    }

    private function rewardReferred(User $newUser, Referral $referral): void
    {
        // 1 Streak Freeze offert
        app(StreakService::class)->awardFreeze($newUser, 'referral_gift');

        \Log::info('Filleul récompensé', [
            'user_id' => $newUser->id,
            'freezes' => $referral->referred_freezes,
        ]);
    }

    private function makeUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }

    private function buildUrl(string $code): string
    {
        $frontend = config('app.frontend_url', config('app.url'));
        return "{$frontend}/register?ref={$code}";
    }

    /**
     * Prochaine récompense bonus (tous les 5 parrainages)
     */
    private function getNextReward(int $count): array
    {
        $next = (int) (ceil(($count + 1) / 5) * 5);

        return [
            'at'          => $next,
            'remaining'   => $next - $count,
            'description' => "1 Streak Freeze bonus à {$next} parrainages",
        ];
    }
}
