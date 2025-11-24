<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Characters
    Route::get('/characters', [CharacterController::class, 'index'])->name('characters.index');
    Route::post('/characters', [CharacterController::class, 'store'])->name('characters.store');
    Route::post('/characters/{character}/switch', [CharacterController::class, 'switch'])->name('characters.switch');

    // Rooms
    Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::get('/rooms/create', [RoomController::class, 'create'])->name('rooms.create');
    Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');

    Route::get('/rooms/{room:slug}', [RoomController::class, 'show'])->name('rooms.show');
    Route::post('/rooms/{room:slug}/messages', [RoomController::class, 'storeMessage'])->name('rooms.messages.store');
    Route::get('/rooms/{room:slug}/messages/latest', [RoomController::class, 'latest'])->name('rooms.messages.latest');

    // Presence ping
    Route::post('/rooms/{room:slug}/presence', [RoomController::class, 'ping'])->name('rooms.presence');

    // Sidebar JSON endpoint
    Route::get('/rooms/sidebar/json', [RoomController::class, 'sidebar'])->name('rooms.sidebar');
});

require __DIR__ . '/auth.php';
