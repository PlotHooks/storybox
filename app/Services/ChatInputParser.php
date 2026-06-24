<?php

namespace App\Services;

use App\Models\Message;
use Illuminate\Validation\ValidationException;

class ChatInputParser
{
    public function parse(string $input): array
    {
        if (preg_match('/^\/me\b(.*)$/i', $input, $matches) === 1) {
            $action = trim($matches[1] ?? '');

            if ($action === '') {
                throw ValidationException::withMessages([
                    'body' => ['Enter an action after /me.'],
                ]);
            }

            return [
                'type' => Message::TYPE_EMOTE,
                'body' => $action,
            ];
        }

        return [
            'type' => Message::TYPE_NORMAL,
            'body' => $input,
        ];
    }
}
