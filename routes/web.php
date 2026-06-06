<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CharacterBlockController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomManagementController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/chat');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified', 'not_banned'])->name('dashboard');

Route::middleware(['auth', 'not_banned'])->group(function () {
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])
        ->middleware('throttle:profile-update')
        ->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Characters
    Route::get('/characters', [CharacterController::class, 'index'])->name('characters.index');
    Route::post('/characters', [CharacterController::class, 'store'])->name('characters.store');
    Route::get('/characters/{character}', [CharacterController::class, 'show'])->name('characters.show');
    Route::get('/characters/{character}/current-room', [CharacterController::class, 'currentRoom'])->name('characters.currentRoom');
    Route::post('/characters/{character}/style', [CharacterController::class, 'updateStyle'])->name('characters.style');
    Route::delete('/characters/{character}', [CharacterController::class, 'destroy'])->name('characters.destroy');
    Route::post('/characters/{blockerCharacter}/blocks/{blockedCharacter}', [CharacterBlockController::class, 'store'])
        ->name('characters.blocks.store');
    Route::delete('/characters/{blockerCharacter}/blocks/{blockedCharacter}', [CharacterBlockController::class, 'destroy'])
        ->name('characters.blocks.destroy');

    Route::get('/chat', [RoomController::class, 'landing'])->name('rooms.landing');
    Route::post('/chat/current-character', [RoomController::class, 'setCurrentCharacter'])->name('rooms.current-character');

    // Rooms
    Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::get('/rooms/create', [RoomController::class, 'create'])->name('rooms.create');
    Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
    Route::post('/rooms/{room:slug}/leave', [RoomController::class, 'leave'])->name('rooms.leave');
    Route::post('/rooms/{room:slug}/presence', [RoomController::class, 'ping'])->name('rooms.presence');
    Route::put('/rooms/{room:slug}/follow', [RoomController::class, 'follow'])->name('rooms.follow');
    Route::patch('/rooms/{room:slug}', [RoomManagementController::class, 'update'])->name('rooms.update');
    Route::delete('/rooms/{room:slug}', [RoomManagementController::class, 'destroy'])->name('rooms.destroy');
    Route::post('/rooms/{room:slug}/whitelist', [RoomManagementController::class, 'addWhitelist'])->name('rooms.whitelist.store');
    Route::delete('/rooms/{room:slug}/whitelist/{character}', [RoomManagementController::class, 'removeWhitelist'])->name('rooms.whitelist.destroy');
    Route::post('/rooms/{room:slug}/blacklist', [RoomManagementController::class, 'addBlacklist'])->name('rooms.blacklist.store');
    Route::delete('/rooms/{room:slug}/blacklist/{character}', [RoomManagementController::class, 'removeBlacklist'])->name('rooms.blacklist.destroy');
    Route::post('/rooms/{room:slug}/moderators', [RoomManagementController::class, 'addModerator'])->name('rooms.moderators.store');
    Route::delete('/rooms/{room:slug}/moderators/{character}', [RoomManagementController::class, 'removeModerator'])->name('rooms.moderators.destroy');

    Route::get('/rooms/sidebar/json', [RoomController::class, 'sidebar'])->name('rooms.sidebar');

    Route::get('/rooms/{room:slug}', [RoomController::class, 'show'])->name('rooms.show');
    Route::get('/rooms/{room:slug}/messages', [RoomController::class, 'latest'])->name('rooms.messages.index');
    Route::post('/rooms/{room:slug}/messages', [RoomController::class, 'storeMessage'])
        ->middleware([
            'resolve_chat_message_character',
            'throttle:chat-message-character',
            'throttle:chat-message-user',
        ])
        ->name('rooms.messages.store');

    Route::get('/rooms/{room:slug}/messages/latest', [RoomController::class, 'latest'])->name('rooms.messages.latest');

    Route::get('/rooms/{room:slug}/roster', [RoomController::class, 'roster'])->name('rooms.roster');

    // Messages
    Route::patch('/messages/{message}', [RoomController::class, 'updateMessage'])->name('messages.update');
    Route::delete('/messages/{message}', [RoomController::class, 'deleteMessage'])->name('messages.delete');
    Route::post('/messages/{message}/reports', [RoomController::class, 'reportMessage'])
        ->middleware('throttle:message-report')
        ->name('messages.report');

    // DMs
    Route::get('/dms', [RoomController::class, 'dmIndex'])->name('dms.index');
    Route::get('/dms/targets', [RoomController::class, 'dmTargets'])->name('dms.targets');
    Route::post('/dms/start', [RoomController::class, 'dmStart'])
        ->middleware('throttle:dm-action')
        ->name('dms.start');
    Route::post('/dms/{room:slug}/archive', [RoomController::class, 'dmArchive'])
        ->middleware('throttle:dm-action')
        ->name('dms.archive');
    Route::post('/dms/{room:slug}/restore', [RoomController::class, 'dmRestore'])
        ->middleware('throttle:dm-action')
        ->name('dms.restore');
    Route::get('/dms/{room:slug}/messages', [RoomController::class, 'dmMessages'])->name('dms.messages.index');
    Route::post('/dms/{room:slug}/messages', [RoomController::class, 'dmSend'])
        ->middleware([
            'resolve_chat_message_character',
            'throttle:chat-message-character',
            'throttle:chat-message-user',
        ])
        ->name('dms.messages.store');
});

require __DIR__ . '/auth.php';
