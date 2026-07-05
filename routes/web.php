<?php

use App\Http\Controllers\CharacterBlockController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\CharacterProfileController;
use App\Http\Controllers\NoticeBoardController;
use App\Http\Controllers\PinnedNotesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RulesController;
use App\Http\Controllers\SiteContentController;
use App\Http\Controllers\RoomToolReadController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomRecoveryController;
use App\Http\Controllers\RoomManagementController;
use App\Http\Controllers\WorldBookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('rooms.landing');
    }

    return view('welcome');
})->name('landing');

Route::get('/about', [SiteContentController::class, 'showPublicCategory'])
    ->defaults('category', 'about-storybox')
    ->name('public.about');
Route::get('/rules-faq', [SiteContentController::class, 'showPublicCollection'])
    ->defaults('collection', 'rules-faq')
    ->name('public.rules-faq');
Route::get('/privacy-policy', [SiteContentController::class, 'showPublicCategory'])
    ->defaults('category', 'privacy-policy')
    ->name('public.privacy-policy');
Route::get('/terms-of-service', [SiteContentController::class, 'showPublicCategory'])
    ->defaults('category', 'terms-of-service')
    ->name('public.terms-of-service');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified', 'not_banned'])->name('dashboard');

Route::get('/characters/{character}/profile', [CharacterProfileController::class, 'show'])
    ->name('characters.profile.show');
Route::get('/characters/{character}/profile/frame', [CharacterProfileController::class, 'frame'])
    ->name('characters.profile.frame');
Route::get('/characters/{character}', [CharacterController::class, 'show'])->name('characters.show');

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
    Route::get('/characters/{character}/manage', [CharacterController::class, 'manage'])->name('characters.manage');
    Route::get('/characters/{character}/current-room', [CharacterController::class, 'currentRoom'])->name('characters.currentRoom');
    Route::post('/characters/{character}/style', [CharacterController::class, 'updateStyle'])->name('characters.style');
    Route::patch('/characters/{character}/active', [CharacterController::class, 'toggleActive'])->name('characters.toggle-active');
    Route::delete('/characters/{character}', [CharacterController::class, 'destroy'])->name('characters.destroy');
    Route::post('/characters/{blockerCharacter}/blocks/{blockedCharacter}', [CharacterBlockController::class, 'store'])
        ->name('characters.blocks.store');
    Route::delete('/characters/{blockerCharacter}/blocks/{blockedCharacter}', [CharacterBlockController::class, 'destroy'])
        ->name('characters.blocks.destroy');

    Route::get('/characters/{character}/profile/edit', [CharacterProfileController::class, 'edit'])
        ->name('characters.profile.edit');
    Route::match(['post', 'put', 'patch'], '/characters/{character}/profile', [CharacterProfileController::class, 'update'])
        ->middleware('throttle:profile-update')
        ->name('characters.profile.update');
    Route::post('/characters/{character}/profile/preview', [CharacterProfileController::class, 'preview'])
        ->middleware('throttle:profile-update')
        ->name('characters.profile.preview');
    Route::get('/characters/{character}/profile/preview/{token}', [CharacterProfileController::class, 'previewShow'])
        ->name('characters.profile.preview.show');
    Route::get('/characters/{character}/profile/preview/{token}/frame', [CharacterProfileController::class, 'previewFrame'])
        ->name('characters.profile.preview.frame');
    Route::get('/characters/{character}/profile/revisions', [CharacterProfileController::class, 'revisions'])
        ->name('characters.profile.revisions');
    Route::post('/characters/{character}/profile/revisions/{revision}/restore', [CharacterProfileController::class, 'restoreRevision'])
        ->name('characters.profile.revisions.restore');
    Route::post('/characters/{character}/profile/disable-custom', [CharacterProfileController::class, 'disableCustom'])
        ->name('characters.profile.disable-custom');
    Route::post('/characters/{character}/profile/enable-custom', [CharacterProfileController::class, 'enableCustom'])
        ->name('characters.profile.enable-custom');
    Route::post('/characters/{character}/profile/revert-default', [CharacterProfileController::class, 'revertToDefault'])
        ->name('characters.profile.revert-default');

    Route::get('/chat', [RoomController::class, 'landing'])->name('rooms.landing');
    Route::post('/chat/current-character', [RoomController::class, 'setCurrentCharacter'])->name('rooms.current-character');
    Route::get('/site-content/{collection}', [SiteContentController::class, 'index'])->name('site-content.index');

    // Rooms
    Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::get('/rooms/recovery', [RoomRecoveryController::class, 'index'])->name('rooms.recovery');
    Route::get('/rooms/create', [RoomController::class, 'create'])->name('rooms.create');
    Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
    Route::post('/rooms/recoverable/{room}/restore', [RoomRecoveryController::class, 'restore'])->name('rooms.recoverable.restore');
    Route::post('/rooms/{room:slug}/leave', [RoomController::class, 'leave'])->name('rooms.leave');
    Route::post('/rooms/{room:slug}/presence', [RoomController::class, 'ping'])->name('rooms.presence');
    Route::put('/rooms/{room:slug}/follow', [RoomController::class, 'follow'])->name('rooms.follow');
    Route::get('/rooms/{room:slug}/profile', [RoomController::class, 'profile'])->name('rooms.profile.show');
    Route::get('/rooms/{room:slug}/history', [RoomController::class, 'history'])->name('rooms.history.show');
    Route::get('/rooms/{room:slug}/profile/frame', [RoomController::class, 'profileFrame'])->name('rooms.profile.frame');
    Route::get('/rooms/{room:slug}/profile/edit', [RoomManagementController::class, 'editProfile'])->name('rooms.profile.edit');
    Route::match(['put', 'patch'], '/rooms/{room:slug}/profile', [RoomManagementController::class, 'updateProfile'])->name('rooms.profile.update');
    Route::patch('/rooms/{room:slug}', [RoomManagementController::class, 'update'])->name('rooms.update');
    Route::delete('/rooms/{room:slug}', [RoomManagementController::class, 'destroy'])->name('rooms.destroy');
    Route::post('/rooms/{room:slug}/whitelist', [RoomManagementController::class, 'addWhitelist'])->name('rooms.whitelist.store');
    Route::delete('/rooms/{room:slug}/whitelist/{character}', [RoomManagementController::class, 'removeWhitelist'])->name('rooms.whitelist.destroy');
    Route::post('/rooms/{room:slug}/blacklist', [RoomManagementController::class, 'addBlacklist'])->name('rooms.blacklist.store');
    Route::delete('/rooms/{room:slug}/blacklist/{character}', [RoomManagementController::class, 'removeBlacklist'])->name('rooms.blacklist.destroy');
    Route::post('/rooms/{room:slug}/account-blacklist', [RoomManagementController::class, 'addAccountBlacklist'])->name('rooms.account-blacklist.store');
    Route::delete('/rooms/{room:slug}/account-blacklist/{character}', [RoomManagementController::class, 'removeAccountBlacklist'])->name('rooms.account-blacklist.destroy');
    Route::post('/rooms/{room:slug}/kick', [RoomManagementController::class, 'kick'])->name('rooms.kick.store');
    Route::get('/rooms/{room:slug}/moderation/characters/{character}', [RoomManagementController::class, 'moderationState'])->name('rooms.moderation.characters.show');
    Route::post('/rooms/{room:slug}/moderators', [RoomManagementController::class, 'addModerator'])->name('rooms.moderators.store');
    Route::delete('/rooms/{room:slug}/moderators/{character}', [RoomManagementController::class, 'removeModerator'])->name('rooms.moderators.destroy');
    Route::get('/rooms/{room:slug}/rules', [RulesController::class, 'index'])->name('rooms.rules.index');
    Route::post('/rooms/{room:slug}/rules', [RulesController::class, 'store'])->name('rooms.rules.store');
    Route::patch('/rooms/{room:slug}/rules/{rule}', [RulesController::class, 'update'])->name('rooms.rules.update');
    Route::delete('/rooms/{room:slug}/rules/{rule}', [RulesController::class, 'destroy'])->name('rooms.rules.destroy');
    Route::post('/rooms/{room:slug}/rules/{rule}/move', [RulesController::class, 'move'])->name('rooms.rules.move');
    Route::get('/rooms/{room:slug}/world-book', [WorldBookController::class, 'index'])->name('rooms.world-book.index');
    Route::post('/rooms/{room:slug}/world-book/recovery/preview', [WorldBookController::class, 'previewArchive'])->name('rooms.world-book.recovery.preview');
    Route::post('/rooms/{room:slug}/world-book/recovery/import', [WorldBookController::class, 'importArchive'])->name('rooms.world-book.recovery.import');
    Route::post('/rooms/{room:slug}/world-book', [WorldBookController::class, 'store'])->name('rooms.world-book.store');
    Route::patch('/rooms/{room:slug}/world-book/{entry}', [WorldBookController::class, 'update'])->name('rooms.world-book.update');
    Route::post('/rooms/{room:slug}/world-book/{entry}/move', [WorldBookController::class, 'move'])->name('rooms.world-book.move');
    Route::post('/rooms/{room:slug}/world-book/{entry}/approve', [WorldBookController::class, 'approve'])->name('rooms.world-book.approve');
    Route::post('/rooms/{room:slug}/world-book/{entry}/reject', [WorldBookController::class, 'reject'])->name('rooms.world-book.reject');
    Route::delete('/rooms/{room:slug}/world-book/{entry}', [WorldBookController::class, 'destroy'])->name('rooms.world-book.destroy');
    Route::get('/rooms/{room:slug}/notice-board', [NoticeBoardController::class, 'index'])->name('rooms.notice-board.index');
    Route::post('/rooms/{room:slug}/notice-board', [NoticeBoardController::class, 'store'])->name('rooms.notice-board.store');
    Route::patch('/rooms/{room:slug}/notice-board/{notice}', [NoticeBoardController::class, 'update'])->name('rooms.notice-board.update');
    Route::delete('/rooms/{room:slug}/notice-board/{notice}', [NoticeBoardController::class, 'destroy'])->name('rooms.notice-board.destroy');
    Route::get('/rooms/{room:slug}/pinned-notes', [PinnedNotesController::class, 'index'])->name('rooms.pinned-notes.index');
    Route::post('/rooms/{room:slug}/pinned-notes', [PinnedNotesController::class, 'store'])->name('rooms.pinned-notes.store');
    Route::patch('/rooms/{room:slug}/pinned-notes/{note}', [PinnedNotesController::class, 'update'])->name('rooms.pinned-notes.update');
    Route::delete('/rooms/{room:slug}/pinned-notes/{note}', [PinnedNotesController::class, 'destroy'])->name('rooms.pinned-notes.destroy');
    Route::post('/rooms/{room:slug}/tool-reads/{tool}', [RoomToolReadController::class, 'store'])->name('rooms.tool-reads.store');

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
    Route::get('/dms/{room:slug}/history', [RoomController::class, 'dmHistory'])->name('dms.history.show');
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
