<?php

use App\Models\Character;
use App\Models\Room;
use App\Services\LegacyRoomOwnerRepairService;
use App\Services\RoomRetentionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('retention:expire-inactive-rooms {--dry-run : Report only without writing changes} {--limit=500 : Maximum number of rooms to process}', function () {
    $limit = max(1, (int) $this->option('limit'));
    $result = app(RoomRetentionService::class)->expireInactiveRooms((bool) $this->option('dry-run'), $limit);

    $this->info(sprintf(
        'Inactive room expiration complete. scanned=%d matched=%d soft_deleted=%d dry_run=%s',
        $result['scanned'],
        $result['matched'],
        $result['soft_deleted'],
        $result['dry_run'] ? 'yes' : 'no',
    ));

    return self::SUCCESS;
})->purpose('Soft-delete inactive public rooms that exceed their retention window.');

Artisan::command('retention:hard-delete-expired-rooms {--dry-run : Report only without writing changes} {--limit=500 : Maximum number of rooms to process}', function () {
    $limit = max(1, (int) $this->option('limit'));
    $result = app(RoomRetentionService::class)->hardDeleteExpiredRooms((bool) $this->option('dry-run'), $limit);

    $this->info(sprintf(
        'Expired room hard delete complete. scanned=%d matched=%d hard_deleted=%d dry_run=%s',
        $result['scanned'],
        $result['matched'],
        $result['hard_deleted'],
        $result['dry_run'] ? 'yes' : 'no',
    ));

    return self::SUCCESS;
})->purpose('Hard-delete soft-deleted public rooms once the recovery window has elapsed.');

Artisan::command('rooms:repair-owners {--dry-run : Report only without writing changes} {--apply : Apply only unambiguous creator-based owner repairs}', function () {
    $repairService = app(LegacyRoomOwnerRepairService::class);
    $apply = (bool) $this->option('apply') && ! $this->option('dry-run');
    $result = $repairService->repairUnownedPublicRooms($apply);
    $inspections = $result['inspections'];

    if ($inspections->isEmpty()) {
        $this->info('No public rooms with missing owners were found.');

        return self::SUCCESS;
    }

    $this->table(
        ['Room ID', 'Room Name', 'Visibility', 'Possible Owner', 'Reason', 'Would Change'],
        $inspections->map(function (array $inspection) {
            $room = $inspection['room'];
            $candidate = $inspection['candidate'];

            return [
                $room->id,
                $room->name,
                $room->visibility ?? Room::VISIBILITY_PUBLIC,
                $candidate instanceof Character ? $candidate->public_handle : '-',
                $inspection['reason'],
                $inspection['would_change'] ? 'yes' : 'no',
            ];
        })->all()
    );

    if ($apply) {
        $this->info("Applied {$result['updated']} owner repair(s).");
    } else {
        $this->info('Dry run only. Re-run with --apply to assign only unambiguous creator-based owners.');
    }

    return self::SUCCESS;
})->purpose('Report or repair missing owners on legacy public rooms.');

Artisan::command('rooms:assign-owner {room_id} {public_handle}', function () {
    $repairService = app(LegacyRoomOwnerRepairService::class);
    $room_id = (string) $this->argument('room_id');
    $public_handle = (string) $this->argument('public_handle');

    if (preg_match('/^\d+$/', trim($public_handle))) {
        $this->error('Use the public handle format Name#ABCD, not a raw character id.');

        return self::FAILURE;
    }

    if (! str_contains(trim($public_handle), '#')) {
        $this->error('Use the full public handle format Name#ABCD.');

        return self::FAILURE;
    }

    $room = Room::find($room_id);
    if (! $room) {
        $this->error("Room {$room_id} was not found.");

        return self::FAILURE;
    }

    if (! $room->isPublicRoom()) {
        $this->error('Only public rooms can be assigned an owner.');

        return self::FAILURE;
    }

    $character = Character::resolvePublicHandle($public_handle);
    if (! $character) {
        $this->error('Target character not found. Use the full public handle format Name#ABCD.');

        return self::FAILURE;
    }

    $repairService->assignOwner($room, $character);

    $this->info("Assigned owner {$character->public_handle} to room {$room->id} ({$room->name}).");

    return self::SUCCESS;
})->purpose('Assign an owner to a public room using a public character handle.');

Schedule::command('retention:expire-inactive-rooms --limit=' . (int) config('retention.rooms.default_limit', 500))
    ->hourly()
    ->withoutOverlapping();

Schedule::command('retention:hard-delete-expired-rooms --limit=' . (int) config('retention.rooms.default_limit', 500))
    ->daily()
    ->withoutOverlapping();
