<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class RetentionReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'retention:report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Affiche la rétention day-1 et day-7 des utilisateurs inscrits';

    public function handle(): int
    {
        $this->reportForDay(1);
        $this->newLine();
        $this->reportForDay(7);

        return self::SUCCESS;
    }

    protected function reportForDay(int $daysAgo): void
    {
        $cohort = $this->cohortSignedUpDaysAgo($daysAgo);
        $retained = $cohort->filter(fn (User $user): bool => $this->hasReturnedSince($user, $daysAgo));

        $rate = $cohort->isEmpty() ? 0 : round($retained->count() / $cohort->count() * 100, 1);
        $this->info("Day-{$daysAgo} retention : {$retained->count()}/{$cohort->count()} ({$rate}%)");

        $this->table(
            ['ID', 'Email', 'Inscrit le', 'Reconnecté'],
            $cohort->map(fn (User $user): array => [
                $user->id,
                $user->email,
                $user->created_at->toDateString(),
                $retained->contains($user) ? 'Oui' : 'Non',
            ])
        );
    }

    protected function cohortSignedUpDaysAgo(int $daysAgo): Collection
    {
        $day = Carbon::today()->subDays($daysAgo);

        return User::query()
            ->whereBetween('created_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->get();
    }

    protected function hasReturnedSince(User $user, int $daysAgo): bool
    {
        if (! $user->last_activity_at) {
            return false;
        }

        return Carbon::parse($user->last_activity_at)->startOfDay()
            ->greaterThan($user->created_at->copy()->startOfDay());
    }
}
