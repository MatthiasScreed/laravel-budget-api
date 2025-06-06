<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialGoal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinancialGoalController extends Controller
{
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
        ]);

        return Auth::user()->financialGoals()->create($data);
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

    private function authorizeAccess(FinancialGoal $goal): void
    {
        if ($goal->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
    }
}
