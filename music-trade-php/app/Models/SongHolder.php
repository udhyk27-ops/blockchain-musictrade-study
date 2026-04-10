<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SongHolder extends Model
{
    protected $table     = 'T_SONG_HOLDER';
    public $incrementing = false;   // 복합 PK (자동증가 없음)
    public $timestamps   = false;

    protected $fillable = [
        'f_song_id',
        'f_user_no',
        'f_role',
        'f_share',
        'f_tx_hash',
        'f_block_number',
        'f_created_at',
        'f_updated_at',
    ];

    public function song()
    {
        return $this->belongsTo(Song::class, 'F_SONG_ID', 'F_ID');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'F_USER_NO', 'F_NO');
    }
}
