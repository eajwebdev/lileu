<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = ['incurred_on', 'category', 'description', 'amount', 'recorded_by'];

    protected $casts = ['incurred_on' => 'date', 'amount' => 'decimal:2'];

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
