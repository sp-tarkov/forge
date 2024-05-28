<?php

use App\Http\Controllers\ModController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::controller(ModController::class)->group(function () {
    Route::get('/mods', 'index')->name('mods');
    Route::get('/mod/{mod}/{slug}', 'show')->where(['id' => '[0-9]+'])->name('mod.show');
});

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});
