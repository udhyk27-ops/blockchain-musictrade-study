<?php

use App\Http\Controllers\Controller;
use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

// TEST
Route::get('/hash-test', function() {
    dd(bcrypt('1'), bcrypt('2'));
});



// 로그인/로그아웃
Route::get('/login',  [LoginController::class, 'index'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');
Route::post('/logout',[LoginController::class, 'destroy'])->name('logout');

// 로그인 없이 접근 가능
Route::get('/song-info', [Controller::class, 'getSongInfo']);

// 로그인 필요
Route::middleware('auth')->group(function() {
    Route::get('/',                   [Controller::class, 'index'])->name('index');
    Route::post('/encode-register',   [Controller::class, 'encodeRegister']);
    Route::post('/encode-set-shares', [Controller::class, 'encodeSetShares']);
    Route::post('/encode-purchase',   [Controller::class, 'encodePurchase']);
});

