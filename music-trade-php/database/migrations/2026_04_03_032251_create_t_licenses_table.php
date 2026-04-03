<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('T_LICENSES', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('song_id');            // T_SONGS.id (FK)
            $table->unsignedBigInteger('song_id_onchain');    // 블록체인 상의 곡 ID
            $table->string('buyer_address', 42);              // 구매자 지갑 주소
            $table->string('amount', 50);                     // 결제 금액 (wei)
            $table->string('tx_hash', 66)->nullable();        // 트랜잭션 해시
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('T_LICENSES');
    }
};
