<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('T_SONGS', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('song_id_onchain');    // 블록체인 상의 곡 ID
            $table->string('title', 200);
            $table->string('producer_address', 42);           // 제작사 지갑 주소
            $table->tinyInteger('active')->default(0);        // 0:비활성 1:활성
            $table->string('tx_hash', 66)->nullable();        // 등록 트랜잭션 해시
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('T_SONGS');
    }
};
