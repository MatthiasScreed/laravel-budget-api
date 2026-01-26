<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserSessionExtended;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserSessionExtended>
 */
class UserSessionExtendedFactory extends Factory
{
    protected $model = UserSessionExtended::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $startedAt = $this->faker->dateTimeBetween('-1 week', 'now');
        $isActive = $this->faker->boolean(30); // 30% chance d'être active

        return [
            'user_id' => User::factory(),
            'session_id' => $this->faker->uuid(),
            'token_id' => $this->faker->sha256(),
            'started_at' => $startedAt,
            'ended_at' => $isActive ? null : $this->faker->dateTimeBetween($startedAt, 'now'),
            'actions_count' => $this->faker->numberBetween(0, 50),
            'xp_earned' => $this->faker->numberBetween(0, 500),
            'pages_visited' => $this->generatePagesVisited(),
            'device_type' => $this->faker->randomElement(['mobile', 'tablet', 'desktop']),
            'device_name' => $this->generateDeviceName(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->generateUserAgent(),
            'device_info' => $this->generateDeviceInfo(),
            'is_current' => $isActive,
            'last_activity_at' => $isActive ? now()->subMinutes($this->faker->numberBetween(1, 60)) : $startedAt,
            'expires_at' => $this->faker->optional()->dateTimeBetween('now', '+1 month'),
        ];
    }

    /**
     * État pour session active
     */
    public function active(): self
    {
        return $this->state([
            'is_current' => true,
            'ended_at' => null,
            'last_activity_at' => now()->subMinutes($this->faker->numberBetween(1, 30)),
        ]);
    }

    /**
     * État pour session terminée
     */
    public function ended(): self
    {
        return $this->state(function (array $attributes) {
            $endedAt = $this->faker->dateTimeBetween($attributes['started_at'], 'now');

            return [
                'is_current' => false,
                'ended_at' => $endedAt,
                'last_activity_at' => $endedAt,
            ];
        });
    }

    /**
     * État pour session mobile
     */
    public function mobile(): self
    {
        return $this->state([
            'device_type' => 'mobile',
            'device_name' => $this->faker->randomElement([
                'iPhone 15 Pro', 'Samsung Galaxy S24', 'Google Pixel 8',
                'iPhone 14', 'OnePlus 12',
            ]),
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            'device_info' => [
                'browser' => 'Safari',
                'platform' => 'iOS',
                'device' => 'iPhone',
                'version' => '17.0',
            ],
        ]);
    }

    /**
     * État pour session desktop
     */
    public function desktop(): self
    {
        return $this->state([
            'device_type' => 'desktop',
            'device_name' => $this->faker->randomElement([
                'MacBook Pro M3', 'Windows PC', 'Ubuntu Desktop',
                'MacBook Air', 'Gaming PC',
            ]),
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'device_info' => [
                'browser' => 'Chrome',
                'platform' => 'macOS',
                'device' => 'MacBook',
                'version' => '120.0.0.0',
            ],
        ]);
    }

    /**
     * État pour session très active (beaucoup d'XP)
     */
    public function highActivity(): self
    {
        return $this->state([
            'actions_count' => $this->faker->numberBetween(20, 100),
            'xp_earned' => $this->faker->numberBetween(200, 1000),
            'pages_visited' => [
                'dashboard', 'transactions', 'goals', 'gaming',
                'achievements', 'leaderboard', 'challenges',
            ],
        ]);
    }

    // ==========================================
    // MÉTHODES UTILITAIRES PRIVÉES
    // ==========================================

    /**
     * Générer des pages visitées réalistes
     */
    private function generatePagesVisited(): array
    {
        $possiblePages = [
            'dashboard', 'transactions', 'transactions/create', 'transactions/edit',
            'goals', 'goals/create', 'categories', 'gaming', 'achievements',
            'leaderboard', 'challenges', 'profile', 'settings',
        ];

        $pageCount = $this->faker->numberBetween(1, 8);

        return $this->faker->randomElements($possiblePages, $pageCount);
    }

    /**
     * Générer un nom d'appareil réaliste
     */
    private function generateDeviceName(): string
    {
        $devices = [
            'iPhone 15 Pro', 'iPhone 14', 'iPhone 13',
            'Samsung Galaxy S24', 'Samsung Galaxy S23',
            'Google Pixel 8', 'Google Pixel 7',
            'MacBook Pro M3', 'MacBook Air M2',
            'Windows PC', 'Gaming PC', 'Ubuntu Desktop',
            'iPad Pro', 'iPad Air', 'Samsung Tab S9',
        ];

        return $this->faker->randomElement($devices);
    }

    /**
     * Générer un User-Agent réaliste
     */
    private function generateUserAgent(): string
    {
        $userAgents = [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        ];

        return $this->faker->randomElement($userAgents);
    }

    /**
     * Générer des infos d'appareil
     */
    private function generateDeviceInfo(): array
    {
        $browsers = ['Chrome', 'Firefox', 'Safari', 'Edge'];
        $platforms = ['Windows', 'macOS', 'Linux', 'iOS', 'Android'];

        return [
            'browser' => $this->faker->randomElement($browsers),
            'platform' => $this->faker->randomElement($platforms),
            'device' => $this->faker->randomElement(['iPhone', 'MacBook', 'Windows PC', 'Android', 'iPad']),
            'version' => $this->faker->numerify('##.#.#'),
        ];
    }
}
