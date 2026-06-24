<?php

namespace App\Services;

class DiceMessageFormatter
{
    public function renderHtml(?array $data): string
    {
        return e($this->renderPlainText($data));
    }

    public function renderStoredBody(string $characterName, ?array $data): string
    {
        return trim($characterName) . ' ' . $this->renderPlainText($data);
    }

    public function renderPlainText(?array $data): string
    {
        if (! is_array($data)) {
            return 'rolled 1d20: [1]';
        }

        $expression = (string) ($data['expression'] ?? '1d20');
        $rolls = is_array($data['rolls'] ?? null) ? $data['rolls'] : [];
        $modifier = (int) ($data['modifier'] ?? 0);
        $total = (int) ($data['total'] ?? 0);

        $parts = array_map(
            static fn ($roll): string => '[' . (int) $roll . ']',
            $rolls
        );

        $summary = 'rolled ' . $expression . ':';

        if ($parts !== []) {
            $summary .= ' ' . implode(' ', $parts);
        }

        if ($modifier > 0) {
            $summary .= ' +' . $modifier;
        } elseif ($modifier < 0) {
            $summary .= ' ' . $modifier;
        }

        if (count($rolls) > 1 || $modifier !== 0) {
            $summary .= ' = ' . $total;
        }

        return $summary;
    }
}
