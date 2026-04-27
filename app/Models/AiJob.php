<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiJob extends Model
{
    protected $table = 'ai_jobs';

    protected $fillable = [
        'user_id',
        'service_type',
        'input_image_url',
        'prompt_payload',
        'status',
        'output_urls',
        'credits_used',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'prompt_payload' => 'array',
            'output_urls'    => 'array',
            'credits_used'   => 'integer',
            'completed_at'   => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
