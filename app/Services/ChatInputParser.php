<?php

namespace App\Services;

use App\Models\Message;
use Illuminate\Validation\ValidationException;

class ChatInputParser
{
    public function __construct(
        private readonly DiceRollParser $diceRollParser,
    ) {
    }

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

        if (preg_match('/^\/roll\b(.*)$/i', $input, $matches) === 1) {
            $roll = $this->diceRollParser->parseAndRoll($matches[1] ?? '');

            return [
                'type' => Message::TYPE_DICE,
                'body' => $roll['expression'],
                'structured_data' => $roll,
            ];
        }

        return [
            'type' => Message::TYPE_NORMAL,
            'body' => $input,
        ];
    }
}
