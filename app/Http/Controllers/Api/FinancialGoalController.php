<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialGoal;
use App\Services\BudgetService; // ✅ Ajouter l'import du BudgetService
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinancialGoalController extends Controller
{
    protected BudgetService $budgetService; // ✅ Ajouter la propriété

    // ✅ Ajouter le constructeur pour injecter le service
    public function __construct(BudgetService $budgetService)
    {
        $this->budgetService = $budgetService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Auth::user()->financialGoals()->with('contributions', 'projections')->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'target_amount' => 'required|numeric|min:0',
            'target_date' => 'nullable|date',
            'priority' => 'nullable|integer|min:1|max:5',
            'description' => 'nullable|string|max:1000',
            'type' => 'nullable|in:savings,debt_payoff,investment,purchase,emergency_fund,other',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
            'monthly_target' => 'nullable|numeric|min:0',
            'is_automatic' => 'nullable|boolean',
            'automatic_amount' => 'nullable|numeric|min:0',
            'automatic_frequency' => 'nullable|in:weekly,monthly,quarterly',
            'notes' => 'nullable|string|max:2000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50'
        ]);

        // ✅ Utiliser BudgetService au lieu de création directe
        $goal = $this->budgetService->createGoal(Auth::user(), $data);

        return $goal; // ✅ Retourner l'objectif créé
    }

    /**
     * Display the specified resource.
     */
    public function show(FinancialGoal $financialGoal): FinancialGoal
    {
        $this->authorizeAccess($financialGoal);
        return $financialGoal->load('contributions', 'projections');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, FinancialGoal $financialGoal): FinancialGoal
    {
        $this->authorizeAccess($financialGoal);

        $data = $request->validate([
            'name' => 'string|max:255',
            'target_amount' => 'numeric|min:0',
            'target_date' => 'nullable|date',
            'priority' => 'integer|min:1|max:5',
            'description' => 'nullable|string|max:1000',
            'type' => 'nullable|in:savings,debt_payoff,investment,purchase,emergency_fund,other',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
            'monthly_target' => 'nullable|numeric|min:0',
            'is_automatic' => 'nullable|boolean',
            'automatic_amount' => 'nullable|numeric|min:0',
            'automatic_frequency' => 'nullable|in:weekly,monthly,quarterly',
            'notes' => 'nullable|string|max:2000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'status' => 'nullable|in:active,completed,paused,cancelled'
        ]);

        $financialGoal->update($data);
        return $financialGoal;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FinancialGoal $financialGoal): \Illuminate\Http\Response
    {
        $this->authorizeAccess($financialGoal);
        $financialGoal->delete();
        return response()->noContent();
    }

    /**
     * Vérifier l'autorisation d'accès à un objectif
     */
    private function authorizeAccess(FinancialGoal $goal): void
    {
        if ($goal->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
    }
}
