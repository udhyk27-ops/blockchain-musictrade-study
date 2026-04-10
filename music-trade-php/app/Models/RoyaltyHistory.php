<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoyaltyHistory extends Model
{
    protected $table      = 'T_ROYALTY_HISTORY';
    protected $primaryKey = 'f_id';
    protected $keyType    = 'int';
    public $incrementing  = true;
    public $timestamps    = false;

    // Oracle 시퀀스명 (yajra/laravel-oci8)
    protected $sequence = 'T_ROYALTY_HISTORY_SEQ';

    protected $fillable = [
        'f_song_id',
        'f_recipient_no',
        'f_role',
        'f_amount',
        'f_tx_hash',
        'f_block_number',
        'f_created_at',
    ];

    public function song()
    {
        return $this->belongsTo(Song::class, 'F_SONG_ID', 'F_ID');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'F_RECIPIENT_NO', 'F_NO');
    }
}
