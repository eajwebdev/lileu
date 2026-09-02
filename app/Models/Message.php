<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'reseller_id', 'reseller_order_id', 'user_id', 'author_role', 'body', 'read_at',
    ];

    protected $casts = ['read_at' => 'datetime'];

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ResellerOrder::class, 'reseller_order_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
