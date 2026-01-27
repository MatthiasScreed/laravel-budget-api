<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialGoal;
use App\Models\GoalContribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoalContributionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $contributions = GoalContribution::with(['financialGoal'])
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $contributions,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'financial_goal_id' => 'required|exists:financial_goals,id',
            'transaction_id' => 'nullable|exists:transactions,id',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
        ]);

        // Vérifier que l'objectif appartient à l'utilisateur connecté
        $goal = FinancialGoal::where('id', $request->financial_goal_id)
            ->where('user_id', auth()->id())
            ->first();

        if (! $goal) {
            return response()->json([
                'success' => false,
                'message' => 'Objectif financier non trouvé',
            ], 404);
        }

        return GoalContribution::create($data);
    }

    /**
     * Display the specified resource.
     */
    public function show(GoalContribution $goalContribution): GoalContribution
    {
        return $goalContribution->load('goal', 'transaction');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, GoalContribution $goalContribution): GoalContribution
    {
        $data = $request->validate([
            'amount' => 'numeric|min:0',
            'date' => 'date',
        ]);

        $goalContribution->update($data);

        return $goalContribution;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GoalContribution $goalContribution): \Illuminate\Http\Response
    {
        $goalContribution->delete();

        return response()->noContent();
    }

    /**
     * Obtenir les contributions d'un objectif spécifique
     */
    public function getByGoal(Request $request, FinancialGoal $financialGoal): JsonResponse
    {
        // Vérifier que l'objectif appartient à l'utilisateur connecté
        if ($financialGoal->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé à cet objectif financier',
            ], 403);
        }

        $contributions = $financialGoal->contributions()
            ->with(['transaction'])
            ->orderBy('date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'goal' => [
                    'id' => $financialGoal->id,
                    'name' => $financialGoal->name,
                    'target_amount' => $financialGoal->target_amount,
                    'current_amount' => $financialGoal->current_amount,
                ],
                'contributions' => $contributions,
                'total_contributions' => $contributions->sum('amount'),
                'contributions_count' => $contributions->count(),
            ],
            'message' => 'Contributions récupérées avec succès',
        ]);
    }
}
