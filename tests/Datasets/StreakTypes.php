<?php

use App\Models\Streak;

dataset('streak_types', [
    'daily_login' => [Streak::TYPE_DAILY_LOGIN, 'Connexion quotidienne'],
    'daily_transaction' => [Streak::TYPE_DAILY_TRANSACTION, 'Transaction quotidienne'],
    'weekly_budget' => [Streak::TYPE_WEEKLY_BUDGET, 'Budget hebdomadaire'],
    'monthly_saving' => [Streak::TYPE_MONTHLY_SAVING, 'Épargne mensuelle'],
]);

dataset('milestone_days', [
    'early_milestone' => [3, true],
    'week_milestone' => [7, true],
    'two_weeks' => [14, true],
    'month' => [30, true],
    'random_day' => [5, false],
    'another_random' => [12, false],
]);
