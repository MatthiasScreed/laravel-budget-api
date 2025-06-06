<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Suggestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SuggestionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Auth::user()->suggestions()->latest()->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:reduce_expense,save_more,switch_category',
            'message' => 'required|string',
            'financial_goal_id' => 'nullable|exists:financial_goals,id',
        ]);

        $data['user_id'] = Auth::id();
        return Suggestion::create($data);
    }

    /**
     * Display the specified resource.
     */
    public function show(Suggestion $suggestion)
    {
        $this->authorizeAccess($suggestion);
        return $suggestion;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Suggestion $suggestion)
    {
        $this->authorizeAccess($suggestion);

        $data = $request->validate([
            'seen' => 'boolean',
        ]);

        $suggestion->update($data);
        return $suggestion;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Suggestion $suggestion)
    {
        $this->authorizeAccess($suggestion);
        $suggestion->delete();
        return response()->noContent();
    }

    private function authorizeAccess(Suggestion $suggestion)
    {
        if ($suggestion->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
    }
}
