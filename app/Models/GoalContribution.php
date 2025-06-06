<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoalContribution extends Model
{
    protected $fillable = [
        'financial_goal_id', 'transaction_id', 'amount', 'date'
    ];

    protected $casts = [
        'date' => 'date'
    ];

    public function goal()
    {
        return $this->belongsTo(FinancialGoal::class, 'financial_goal_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
