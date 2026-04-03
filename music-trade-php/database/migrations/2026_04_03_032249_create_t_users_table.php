<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('T_USERS', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email', 100)->unique();
            $table->string('password', 255);
            $table->string('wallet_address', 42)->nullable(); // MetaMask 주소
            $table->tinyInteger('role');                      // 1:제작사 2:작곡가 3:작사가 4:가수 5:편곡자 6:유통사 7:사용자
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('T_USERS');
    }
};
