<?php

return [
    'chat_message_character_max' => env('RATE_CHAT_MESSAGE_CHARACTER_MAX', 5),
    'chat_message_character_decay' => env('RATE_CHAT_MESSAGE_CHARACTER_DECAY', 10),

    'chat_message_user_max' => env('RATE_CHAT_MESSAGE_USER_MAX', 20),
    'chat_message_user_decay' => env('RATE_CHAT_MESSAGE_USER_DECAY', 60),

    'message_report_max' => env('RATE_MESSAGE_REPORT_MAX', 5),
    'message_report_decay' => env('RATE_MESSAGE_REPORT_DECAY', 60),

    'dm_action_max' => env('RATE_DM_ACTION_MAX', 30),
    'dm_action_decay' => env('RATE_DM_ACTION_DECAY', 60),

    'profile_update_max' => env('RATE_PROFILE_UPDATE_MAX', 10),
    'profile_update_decay' => env('RATE_PROFILE_UPDATE_DECAY', 60),
];
