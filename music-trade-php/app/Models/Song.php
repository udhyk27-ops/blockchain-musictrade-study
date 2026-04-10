<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Song extends Model
{
    protected $table      = 'T_SONG';
    protected $primaryKey = 'f_id';
    protected $keyType    = 'int';
    public $incrementing  = true;
    public $timestamps    = false;

    // Oracle 시퀀스명 (yajra/laravel-oci8)
    protected $sequence = 'T_SONG_SEQ';

    protected $fillable = [
        'f_song_id',
        'f_title',
        'f_producer_no',
        'f_active',
        'f_total_revenue',
        'f_tx_hash',
        'f_block_number',
        'f_created_at',
        'f_updated_at',
    ];

    public function producer()
    {
        return $this->belongsTo(User::class, 'F_PRODUCER_NO', 'F_NO');
    }

    public function holders()
    {
        return $this->hasMany(SongHolder::class, 'F_SONG_ID', 'F_ID');
    }

    public function licensePurchases()
    {
        return $this->hasMany(LicensePurchase::class, 'F_SONG_ID', 'F_ID');
    }
}
