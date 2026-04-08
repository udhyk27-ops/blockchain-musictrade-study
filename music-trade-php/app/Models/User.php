<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table      = 'T_USER';
    protected $primaryKey = 'f_id';
    protected $keyType    = 'string';
    public $incrementing  = false;
    public $timestamps    = false;

    protected $fillable = [
        'f_id',
        'f_password',
        'f_mail',
        'f_wallet_address',
        'f_reg_date',
        'f_mod_date',
        'f_status',
    ];

    protected $hidden = [
        'f_password',
    ];

    protected function casts(): array
    {
        return [
            'f_mod_date' => 'datetime',
            'f_password' => 'hashed',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'f_password';
    }

    public function getRememberTokenName()
    {
        return null;
    }
}
