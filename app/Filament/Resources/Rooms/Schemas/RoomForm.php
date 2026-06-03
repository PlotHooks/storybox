<?php

namespace App\Filament\Resources\Rooms\Schemas;

use App\Models\Character;
use App\Models\Room;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('created_by')
                    ->numeric(),
                Select::make('owner_character_id')
                    ->label('Owner Character')
                    ->nullable()
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search): array {
                        return Character::query()
                            ->where('name', 'like', '%' . trim($search) . '%')
                            ->orderBy('name')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (Character $character) => [
                                $character->id => $character->public_handle,
                            ])
                            ->all();
                    })
                    ->getOptionLabelUsing(fn ($value): ?string => $value ? Character::find($value)?->public_handle : null)
                    ->helperText(fn (?Room $record): string => $record?->isDm()
                        ? 'DM conversations do not support room owners.'
                        : 'Leave empty for legacy or admin-created rooms that still need manual owner assignment.')
                    ->disabled(fn (?Room $record): bool => $record?->isDm() ?? false),
                TextInput::make('type')
                    ->required()
                    ->default('public'),
                Select::make('visibility')
                    ->label('Visibility')
                    ->options([
                        Room::VISIBILITY_PUBLIC => 'Public',
                        Room::VISIBILITY_HIDDEN => 'Hidden',
                    ])
                    ->required()
                    ->default(Room::VISIBILITY_PUBLIC)
                    ->helperText(fn (?Room $record): ?string => $record?->isDm() ? 'DM conversations are not owner-managed rooms.' : null)
                    ->disabled(fn (?Room $record): bool => $record?->isDm() ?? false),
            ]);
    }
}
