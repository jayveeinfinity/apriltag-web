<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TagDetectionController;

Route::get('/', function () {
    return view('detector');
});

Route::post('/api/log-detection', [TagDetectionController::class, 'store']);
