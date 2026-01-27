<?php

use App\Models\Streak;
use App\Models\User;
use App\Services\StreakService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StreakServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StreakService $streakService;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->streakService = new StreakService;
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    #[Test]
    public function it_can_create_new_streak()
    {
        $result = $this->streakService->triggerStreak($this->user, Streak::TYPE_DAILY_LOGIN);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['streak']['current_count']);
        $this->assertEquals(1, $result['streak']['best_count']);
        $this->assertGreaterThan(0, $result['bonus_xp']);
    }

    #[Test]
    public function it_increments_streak_on_consecutive_days()
    {
        // Jour 1
        $result1 = $this->streakService->triggerStreak($this->user, Streak::TYPE_DAILY_LOGIN);
        $this->assertEquals(1, $result1['streak']['current_count']);

        // Simuler le passage au jour suivant
        $streak = $this->user->streaks()->first();
        $streak->update(['last_activity_date' => now()->subDay()]);

        // Jour 2
        $result2 = $this->streakService->triggerStreak($this->user, Streak::TYPE_DAILY_LOGIN);
        $this->assertEquals(2, $result2['streak']['current_count']);
    }

    #[Test]
    public function it_resets_streak_if_day_is_skipped()
    {
        // Jour 1
        $this->streakService->triggerStreak($this->user, Streak::TYPE_DAILY_LOGIN);

        // Simuler le passage de 2 jours (jour sauté)
        $streak = $this->user->streaks()->first();
        $streak->update(['last_activity_date' => now()->subDays(2)]);

        // Jour 3 (après avoir sauté le jour 2)
        $result = $this->streakService->triggerStreak($this->user, Streak::TYPE_DAILY_LOGIN);
        $this->assertEquals(1, $result['streak']['current_count']); // Reset à 1
    }

    #[Test]
    public function it_prevents_multiple_increments_same_day()
    {
        // Premier appel
        $result1 = $this->streakService->triggerStreak($this->user, Streak::TYPE_DAILY_LOGIN);
        $this->assertTrue($result1['success']);

        // Deuxième appel le même jour
        $result2 = $this->streakService->triggerStreak($this->user, Streak::TYPE_DAILY_LOGIN);
        $this->assertFalse($result2['success']);
        $this->assertStringContainsString('déjà comptabilisée', $result2['message']);
    }

    #[Test]
    public function it_updates_best_count_when_current_exceeds_it()
    {
        // ✅ APPROCHE ALTERNATIVE : Créer directement la streak avec factory
        $streak = Streak::factory()->create([
            'user_id' => $this->user->id,
            'type' => Streak::TYPE_DAILY_LOGIN,
            'current_count' => 2,
            'best_count' => 2,
            'last_activity_date' => now()->subDay(), // Hier
            'is_active' => true,
        ]);

        // Déclencher aujourd'hui pour passer à 3
        $result = $this->streakService->triggerStreak($this->user, Streak::TYPE_DAILY_LOGIN);

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['streak']['current_count']);
        $this->assertEquals(3, $result['streak']['best_count']);
    }

    #[Test]
    public function it_calculates_milestone_correctly()
    {
        $streak = Streak::factory()->create([
            'user_id' => $this->user->id,
            'type' => Streak::TYPE_DAILY_LOGIN,
            'current_count' => 7, // Milestone
            'best_count' => 7,
        ]);

        $this->assertTrue($streak->isAtMilestone());
        $this->assertEquals(14, $streak->getNextMilestone());
    }

    #[Test]
    public function it_calculates_bonus_availability()
    {
        $streak = Streak::factory()->create([
            'user_id' => $this->user->id,
            'type' => Streak::TYPE_DAILY_LOGIN,
            'current_count' => 7, // Divisible par 7
            'best_count' => 7,
            'bonus_claimed_at' => null,
        ]);

        $this->assertTrue($streak->canClaimBonus());
        $this->assertGreaterThan(0, $streak->calculateBonusXp());
    }
}
