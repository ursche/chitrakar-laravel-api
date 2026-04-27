<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'price_npr',
        'credits',
        'popular',
    ];

    protected function casts(): array
    {
        return [
            'price_npr' => 'integer',
            'credits'   => 'integer',
            'popular'   => 'boolean',
        ];
    }
}
