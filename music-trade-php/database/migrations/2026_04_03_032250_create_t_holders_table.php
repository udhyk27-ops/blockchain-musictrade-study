<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('T_HOLDERS', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('song_id');            // T_SONGS.id (FK)
            $table->unsignedBigInteger('song_id_onchain');    // 블록체인 상의 곡 ID
            $table->string('wallet_address', 42);             // 권리자 지갑 주소
            $table->tinyInteger('role');                      // 1:제작사 2:작곡가 3:작사가 4:가수 5:편곡자
            $table->unsignedInteger('share');                 // 지분율 (basis points, 10000=100%)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('T_HOLDERS');
    }
};
