<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicensePurchase extends Model
{
    protected $table      = 'T_LICENSE_PURCHASE';
    protected $primaryKey = 'f_id';
    protected $keyType    = 'int';
    public $incrementing  = true;
    public $timestamps    = false;

    // Oracle 시퀀스명 (yajra/laravel-oci8)
    protected $sequence = 'T_LICENSE_PURCHASE_SEQ';

    protected $fillable = [
        'f_song_id',
        'f_buyer_no',
        'f_amount',
        'f_tx_hash',
        'f_block_number',
        'f_purchased_at',
    ];

    public function song()
    {
        return $this->belongsTo(Song::class, 'F_SONG_ID', 'F_ID');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'F_BUYER_NO', 'F_NO');
    }
}
