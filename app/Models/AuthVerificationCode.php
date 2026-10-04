<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthVerificationCode extends Model
{
    protected $fillable = [
        'email',
        'portal',
        'purpose',
        'code_hash',
        'reset_token_hash',
        'attempts',
        'verified_at',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
