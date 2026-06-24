<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class DiceRollParser
{
    public const MAX_DICE_COUNT = 100;
    public const MAX_DIE_SIZE = 1000;
    public const MAX_EXPRESSION_LENGTH = 50;

    public function parseAndRoll(?string $input): array
    {
        $expression = trim((string) $input);

        if ($expression === '') {
            $expression = '1d20';
        }

        if (mb_strlen($expression) > self::MAX_EXPRESSION_LENGTH) {
            throw ValidationException::withMessages([
                'body' => ['Roll expressions must be 50 characters or fewer.'],
            ]);
        }

        if (preg_match('/^(?:(\d*)d(\d+))\s*([+-]\s*\d+)?$/i', $expression, $matches) !== 1) {
            throw ValidationException::withMessages([
                'body' => ['Enter a valid roll like /roll 2d8+3.'],
            ]);
        }

        $diceCount = (int) ($matches[1] !== '' ? $matches[1] : 1);
        $dieSize = (int) ($matches[2] ?? 0);
        $modifier = isset($matches[3]) ? (int) preg_replace('/\s+/', '', $matches[3]) : 0;

        if ($diceCount < 1) {
            throw ValidationException::withMessages([
                'body' => ['Roll at least one die.'],
            ]);
        }

        if ($diceCount > self::MAX_DICE_COUNT) {
            throw ValidationException::withMessages([
                'body' => ['You can roll up to 100 dice at once.'],
            ]);
        }

        if ($dieSize < 1) {
            throw ValidationException::withMessages([
                'body' => ['Dice must have at least one side.'],
            ]);
        }

        if ($dieSize > self::MAX_DIE_SIZE) {
            throw ValidationException::withMessages([
                'body' => ['Dice can have up to 1000 sides.'],
            ]);
        }

        $normalizedExpression = sprintf('%dd%d', $diceCount, $dieSize);

        if ($modifier > 0) {
            $normalizedExpression .= '+' . $modifier;
        } elseif ($modifier < 0) {
            $normalizedExpression .= (string) $modifier;
        }

        $rolls = [];

        for ($i = 0; $i < $diceCount; $i++) {
            $rolls[] = random_int(1, $dieSize);
        }

        return [
            'version' => 1,
            'notation' => 'standard',
            'expression' => $normalizedExpression,
            'dice_count' => $diceCount,
            'die_size' => $dieSize,
            'modifier' => $modifier,
            'rolls' => $rolls,
            'total' => array_sum($rolls) + $modifier,
            'terms' => array_values(array_filter([
                [
                    'type' => 'dice',
                    'count' => $diceCount,
                    'size' => $dieSize,
                    'results' => $rolls,
                ],
                $modifier !== 0 ? [
                    'type' => 'modifier',
                    'value' => $modifier,
                ] : null,
            ])),
        ];
    }
}
