<?php

namespace App\Services;

use App\Models\Message;

class RoomHistoryExportFormatter
{
    public function __construct(
        private readonly DiceMessageFormatter $diceFormatter,
    ) {
    }

    public function rowsFromMessages(iterable $messages): array
    {
        $rows = [];

        foreach ($messages as $message) {
            $rows[] = $this->rowFromMessage($message);
        }

        return $rows;
    }

    public function formatTranscript(array $rows): string
    {
        return implode("\n", array_map(
            static fn (array $row): string => (string) ($row['transcript_line'] ?? ''),
            $rows,
        ));
    }

    public function formatStorybox(array $rows): string
    {
        return implode("\n", array_map(
            static fn (array $row): string => (string) ($row['storybox_line'] ?? ''),
            $rows,
        ));
    }

    public function formatTsv(array $rows): string
    {
        $headers = [
            'timestamp',
            'sender_character_name',
            'sender_character_id',
            'dm_thread_id',
            'message_type',
            'body',
            'roll_expression',
            'roll_result',
            'edited',
            'deleted',
        ];

        $lines = [implode("\t", $headers)];

        foreach ($rows as $row) {
            $values = [
                $row['timestamp'] ?? '',
                $row['sender_character_name'] ?? $row['character_name'] ?? '',
                $row['sender_character_id'] ?? $row['character_id'] ?? '',
                $row['dm_thread_id'] ?? '',
                $row['message_type'] ?? '',
                $row['body'] ?? '',
                $row['roll_expression'] ?? '',
                $row['roll_result'] ?? '',
                ! empty($row['edited']) ? '1' : '0',
                ! empty($row['deleted']) ? '1' : '0',
            ];

            $lines[] = implode("\t", array_map(
                static fn ($value): string => str_replace(["\r", "\n", "\t"], [' ', ' ', ' '], (string) ($value ?? '')),
                $values,
            ));
        }

        return implode("\n", $lines);
    }

    public function formatCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, [
            'timestamp',
            'sender_character_name',
            'sender_character_id',
            'dm_thread_id',
            'message_type',
            'body',
            'roll_expression',
            'roll_result',
            'edited',
            'deleted',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['timestamp'] ?? '',
                $row['sender_character_name'] ?? $row['character_name'] ?? '',
                $row['sender_character_id'] ?? $row['character_id'] ?? '',
                $row['dm_thread_id'] ?? '',
                $row['message_type'] ?? '',
                str_replace(["\r", "\n"], [' ', ' '], (string) ($row['body'] ?? '')),
                $row['roll_expression'] ?? '',
                str_replace(["\r", "\n"], [' ', ' '], (string) ($row['roll_result'] ?? '')),
                ! empty($row['edited']) ? '1' : '0',
                ! empty($row['deleted']) ? '1' : '0',
            ]);
        }

        rewind($handle);
        $contents = stream_get_contents($handle) ?: '';
        fclose($handle);

        return rtrim($contents, "\n");
    }

    public function rowFromMessage(Message $message): array
    {
        $characterName = (string) ($message->character?->name ?? 'Unknown');
        $timestamp = $message->created_at?->copy()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s T') ?? '';
        $deleted = (bool) $message->deleted_at;
        $edited = $message->updated_at !== null
            && $message->created_at !== null
            && ! $message->updated_at->equalTo($message->created_at);

        $rollExpression = '';
        $rollResult = '';

        if ($message->isDice()) {
            $rollExpression = (string) ($message->structured_data['expression'] ?? '');
            $rollResult = $this->diceFormatter->renderPlainText($message->structured_data);
        }

        if ($deleted) {
            $body = '[deleted]';
            $storyboxLine = '[deleted]';
        } elseif ($message->isEmote()) {
            $body = (string) $message->body;
            $storyboxLine = '/me ' . $body;
        } elseif ($message->isDice()) {
            $body = $rollResult;
            $storyboxLine = $rollExpression !== '' ? '/roll ' . $rollExpression : $rollResult;
        } else {
            $body = (string) $message->body;
            $storyboxLine = $body;
        }

        $transcriptLine = sprintf(
            '[%s] %s%s%s',
            $timestamp,
            $characterName,
            ($message->isEmote() || $message->isDice()) ? ' ' : ': ',
            $body,
        );

        return [
            'id' => (int) $message->id,
            'timestamp' => $timestamp,
            'character_name' => $characterName,
            'sender_character_name' => $characterName,
            'character_id' => $message->character_id !== null ? (int) $message->character_id : null,
            'sender_character_id' => $message->character_id !== null ? (int) $message->character_id : null,
            'dm_thread_id' => $message->room_id !== null ? (int) $message->room_id : null,
            'message_type' => (string) ($message->type ?? Message::TYPE_NORMAL),
            'body' => $body,
            'roll_expression' => $rollExpression,
            'roll_result' => $rollResult,
            'edited' => $edited,
            'deleted' => $deleted,
            'transcript_line' => $transcriptLine,
            'storybox_line' => $storyboxLine,
        ];
    }
}
