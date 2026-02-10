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
    Route::get('/characters/{character}', [CharacterController::class, 'show'])->name('characters.show');
    Route::get('/characters/{character}/current-room', [CharacterController::class, 'currentRoom'])->name('characters.currentRoom');
    Route::post('/characters/{character}/style', [CharacterController::class, 'updateStyle'])->name('characters.style');

    // Rooms
    Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::get('/rooms/create', [RoomController::class, 'create'])->name('rooms.create');
    Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
    Route::post('/rooms/{room:slug}/leave', [RoomController::class, 'leave'])->name('rooms.leave');
    Route::post('/rooms/{room:slug}/presence', [RoomController::class, 'ping'])->name('rooms.presence');

    Route::get('/rooms/sidebar/json', [RoomController::class, 'sidebar'])->name('rooms.sidebar');

    Route::get('/rooms/{room:slug}', [RoomController::class, 'show'])->name('rooms.show');
    Route::post('/rooms/{room:slug}/messages', [RoomController::class, 'storeMessage'])->name('rooms.messages.store');

    // NOTE: your current RoomController@latest enforces DM membership only.
    // Keep this route, but you should fix latest() to support public rooms or split it into separate endpoints.
    Route::get('/rooms/{room:slug}/messages/latest', [RoomController::class, 'latest'])->name('rooms.messages.latest');

    Route::get('/rooms/{room:slug}/roster', [RoomController::class, 'roster'])->name('rooms.roster');

    // Messages
    Route::patch('/messages/{message}', [RoomController::class, 'updateMessage'])->name('messages.update');
    Route::delete('/messages/{message}', [RoomController::class, 'deleteMessage'])->name('messages.delete');

    // DMs
    Route::get('/dms', [RoomController::class, 'dmIndex'])->name('dms.index');
    Route::post('/dms/start', [RoomController::class, 'dmStart'])->name('dms.start');
    Route::get('/dms/{room:slug}/messages', [RoomController::class, 'dmMessages'])->name('dms.messages.index');
    Route::post('/dms/{room:slug}/messages', [RoomController::class, 'dmSend'])->name('dms.messages.store');
});

require __DIR__ . '/auth.php';
