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
    protected $primaryKey = 'f_no';     // 숫자 PK (FK 관계 기준)
    protected $keyType    = 'int';
    public $incrementing  = true;
    public $timestamps    = false;

    // Oracle 시퀀스명 (yajra/laravel-oci8)
    protected $sequence = 'T_USER_SEQ';

    protected $fillable = [
        'f_id',
        'f_password',
        'f_name',
        'f_mail',
        'f_wallet_address',
        'f_private_key',
        'f_regdate',
        'f_moddate',
        'f_status',
        'f_role',
    ];

    protected $hidden = [
        'f_password',
        'f_private_key',
    ];

    protected function casts(): array
    {
        return [
            'f_password' => 'hashed',
        ];
    }

    /**
     * Auth 시스템: 비밀번호 컬럼명 지정
     */
    public function getAuthPasswordName(): string
    {
        return 'f_password';
    }

    /**
     * Auth 시스템: remember token 미사용
     */
    public function getRememberTokenName()
    {
        return null;
    }

    public function songs()
    {
        return $this->hasMany(Song::class, 'F_PRODUCER_NO', 'F_NO');
    }

    public function songHoldings()
    {
        return $this->hasMany(SongHolder::class, 'F_USER_NO', 'F_NO');
    }

    public function royaltyHistories()
    {
        return $this->hasMany(RoyaltyHistory::class, 'F_RECIPIENT_NO', 'F_NO');
    }
}
