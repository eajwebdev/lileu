<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $fillable = ['purchased_on', 'supplier', 'reference', 'description', 'amount', 'recorded_by'];

    protected $casts = ['purchased_on' => 'date', 'amount' => 'decimal:2'];

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
