<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Streak;
use App\Models\Transaction;
use App\Services\StreakService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    protected StreakService $streakService;

    public function __construct(StreakService $streakService)
    {
        $this->streakService = $streakService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Auth::user()->transactions()->with('category')->latest()->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric',
            'description' => 'nullable|string',
            'transaction_date' => 'required|date',
            'is_recurring' => 'boolean',
            'recurrence' => 'nullable|string',
        ]);

        $user = auth()->user();
        $transaction = $user->transactions()->create($data);

        // 🔥 DÉCLENCHER LA STREAK DE TRANSACTION
        $streakResult = $this->streakService->triggerStreak($user, Streak::TYPE_DAILY_TRANSACTION);

        // 🏆 VÉRIFIER LES SUCCÈS
        $unlockedAchievements = $user->checkAndUnlockAchievements();

        return response()->json([
            'success' => true,
            'data' => [
                'transaction' => $transaction->load('category'),
                'streak_result' => $streakResult,
                'updated_streaks' => $this->streakService->getUserStreaks($user),
                'unlocked_achievements' => $unlockedAchievements
            ],
            'message' => $streakResult['message'] ?? 'Transaction créée avec succès'
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Transaction $transaction)
    {
        $this->authorizeAccess($transaction);
        return $transaction->load('category');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Transaction $transaction)
    {
        $this->authorizeAccess($transaction);

        $data = $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'type' => 'in:income,expense',
            'amount' => 'numeric',
            'description' => 'nullable|string',
            'date' => 'date',
            'is_recurring' => 'boolean',
            'recurrence' => 'nullable|string',
        ]);

        $transaction->update($data);
        return $transaction;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Transaction $transaction)
    {
        $this->authorizeAccess($transaction);
        $transaction->delete();

        return response()->noContent();
    }

    private function authorizeAccess(Transaction $transaction)
    {
        if ($transaction->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
    }
}
