<?php

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;

Route::get('/',                   [Controller::class, 'index']);
Route::get('/song-info',          [Controller::class, 'getSongInfo']);
Route::post('/encode-register',   [Controller::class, 'encodeRegister']);
Route::post('/encode-set-shares', [Controller::class, 'encodeSetShares']);
Route::post('/encode-purchase',   [Controller::class, 'encodePurchase']);
