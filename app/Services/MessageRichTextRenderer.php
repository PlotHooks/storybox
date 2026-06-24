<?php

namespace App\Services;

class MessageRichTextRenderer
{
    private const TAG_MAP = [
        'b' => ['open' => '<strong>', 'close' => '</strong>'],
        'i' => ['open' => '<em>', 'close' => '</em>'],
        'u' => ['open' => '<span class="msg-rich-underline">', 'close' => '</span>'],
        's' => ['open' => '<span class="msg-rich-strike">', 'close' => '</span>'],
        'small' => ['open' => '<span class="msg-rich-small">', 'close' => '</span>'],
        'large' => ['open' => '<span class="msg-rich-large">', 'close' => '</span>'],
    ];

    public function render(?string $text): string
    {
        $text = (string) ($text ?? '');
        $pattern = '/\[(\/?)(b|i|u|s|small|large)\]/i';

        preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);

        $stack = [
            [
                'tag' => null,
                'buffer' => '',
            ],
        ];

        $cursor = 0;

        foreach ($matches[0] as $index => [$token, $offset]) {
            $literal = substr($text, $cursor, $offset - $cursor);
            $stack[array_key_last($stack)]['buffer'] .= e($literal);

            $isClosing = ($matches[1][$index][0] ?? '') === '/';
            $tag = strtolower($matches[2][$index][0] ?? '');

            if (! isset(self::TAG_MAP[$tag])) {
                $stack[array_key_last($stack)]['buffer'] .= e($token);
                $cursor = $offset + strlen($token);
                continue;
            }

            if (! $isClosing) {
                $stack[] = [
                    'tag' => $tag,
                    'buffer' => '',
                ];
                $cursor = $offset + strlen($token);
                continue;
            }

            $current = $stack[array_key_last($stack)];

            if (count($stack) > 1 && $current['tag'] === $tag) {
                array_pop($stack);
                $stack[array_key_last($stack)]['buffer'] .= self::TAG_MAP[$tag]['open']
                    . $current['buffer']
                    . self::TAG_MAP[$tag]['close'];
            } else {
                $stack[array_key_last($stack)]['buffer'] .= e($token);
            }

            $cursor = $offset + strlen($token);
        }

        $stack[array_key_last($stack)]['buffer'] .= e(substr($text, $cursor));

        while (count($stack) > 1) {
            $current = array_pop($stack);
            $stack[array_key_last($stack)]['buffer'] .= e('[' . $current['tag'] . ']') . $current['buffer'];
        }

        return $stack[0]['buffer'];
    }
}