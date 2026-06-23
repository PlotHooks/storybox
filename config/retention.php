<?php

return [
    'rooms' => [
        'recovery_window_days' => 90,
        'command_batch_size' => 100,
        'default_limit' => 500,
        'tiers' => [
            'new' => [
                'active_room_limit' => 1,
                'inactive_after_hours' => 24,
            ],
            'mature' => [
                'starts_after_days' => 30,
                'active_room_limit' => 10,
                'inactive_after_hours' => 168,
            ],
            'premium' => [
                'enabled' => false,
                'active_room_limit' => 10,
                'inactive_after_hours' => 168,
            ],
        ],
    ],
];
