<?php

use App\Http\Controllers\Controller;
use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

// 로그인/로그아웃
Route::get('/login',    [LoginController::class, 'index'])->name('login');
Route::post('/login',   [LoginController::class, 'store'])->name('login.store');
Route::post('/logout',  [LoginController::class, 'destroy'])->name('logout');

// 회원가입
Route::get('/register',  [LoginController::class, 'registerForm'])->name('register');
Route::post('/register', [LoginController::class, 'register'])->name('register.store');

// 로그인 필요
Route::middleware('auth')->group(function () {

    // 대시보드
    Route::get('/', [Controller::class, 'index'])->name('index');

    // 트랜잭션 (서버 사이드 서명)
    Route::post('/song/register',  [Controller::class, 'registerSong'])->name('song.register');
    Route::post('/song/set-shares',[Controller::class, 'setShares'])->name('song.setShares');
    Route::post('/song/purchase',  [Controller::class, 'purchaseLicense'])->name('song.purchase');

    // 조회 (DB)
    Route::get('/song/info',          [Controller::class, 'getSongInfo'])->name('song.info');
    Route::get('/song/holders',       [Controller::class, 'getHolders'])->name('song.holders');
    Route::get('/license/history',    [Controller::class, 'getLicenseHistory'])->name('license.history');
    Route::get('/royalty/history',    [Controller::class, 'getRoyaltyHistory'])->name('royalty.history');
    Route::get('/song/count',         [Controller::class, 'getSongCount'])->name('song.count');
});
