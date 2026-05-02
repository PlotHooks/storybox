<?php

namespace App\Filament\Pages;

use App\Events\ModerationMessageCreated;
use App\Models\Message;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class LiveModeration extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Live Moderation';

    protected static string|\UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?string $title = 'Live Moderation';

    protected string $view = 'filament.pages.live-moderation';

    public array $messages = [];

    public function mount(): void
    {
        $this->messages = $this->latestMessages();
    }

    public static function canAccess(): bool
    {
        return Auth::check() && Gate::allows('accessFilament', Auth::user());
    }

    public function deleteMessage(int $messageId): void
    {
        abort_unless(static::canAccess(), 403);

        $message = Message::withTrashed()->findOrFail($messageId);

        abort_unless(Gate::allows('modify-message', $message), 403);

        if (! $message->trashed()) {
            $message->deleted_by = Auth::id();
            $message->save();
            $message->delete();
        }

        $this->messages = collect($this->messages)
            ->map(fn (array $item): array => (int) $item['id'] === $message->id
                ? [...$item, 'deleted' => true]
                : $item)
            ->all();
    }

    private function latestMessages(): array
    {
        return Message::query()
            ->withTrashed()
            ->with(['room', 'character', 'user'])
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (Message $message): array => ModerationMessageCreated::payloadFor($message))
            ->all();
    }
}
