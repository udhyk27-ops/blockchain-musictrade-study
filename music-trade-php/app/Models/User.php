<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'T_USER';
    protected $primaryKey = 'F_ID';
    protected $keyType = 'string';


    protected $fillable = [
        'F_ID',
        'F_PASSWORD',
        'F_MAIL',
        'F_WALLET_ADDRESS',
        'F_REG_DATE',
        'F_MOD_DATE'
    ];

    protected $hidden = [
        'F_PASSWORD',
    ];



    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'F_MOD_DATE' => 'datetime',
            'F_PASSWORD' => 'hashed',
        ];
    }
}
