<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'package_id',
        'amount_npr',
        'payment_gateway',
        'status',
        'credits_awarded',
        'gateway_tx_id',
        'transaction_uuid',
    ];

    protected function casts(): array
    {
        return [
            'amount_npr'      => 'integer',
            'credits_awarded' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
