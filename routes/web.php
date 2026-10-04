<?php

use Illuminate\Support\Facades\Route;

Route::get('/login', function () {
    return view('app');
})->name('login');

// Catch-all route for React SPA
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
