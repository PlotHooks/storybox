<?php

namespace App\Filament\Resources\Rooms\Schemas;

use App\Models\RoomAccessEntry;
use App\Models\RoomCharacterRole;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RoomInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user_id')
                    ->numeric(),
                TextEntry::make('name'),
                TextEntry::make('slug'),
                TextEntry::make('description')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('type'),
                TextEntry::make('visibility')
                    ->placeholder('public'),
                TextEntry::make('owner_character_id')
                    ->label('Owner Character ID')
                    ->placeholder('No owner assigned'),
                TextEntry::make('owner_public_handle')
                    ->label('Owner Character')
                    ->state(fn ($record): string => $record->ownerCharacter?->public_handle ?? 'No owner assigned')
                    ->placeholder('No owner assigned'),
                TextEntry::make('moderators')
                    ->state(fn ($record) => $record->roomCharacterRoles()
                        ->where('role', RoomCharacterRole::ROLE_MODERATOR)
                        ->with('character:id,name')
                        ->get()
                        ->map(fn (RoomCharacterRole $role) => $role->character?->public_handle)
                        ->filter()
                        ->sort()
                        ->implode(', '))
                    ->placeholder('-'),
                TextEntry::make('whitelist')
                    ->state(fn ($record) => $record->roomAccessEntries()
                        ->where('type', RoomAccessEntry::TYPE_WHITELIST)
                        ->with('character:id,name')
                        ->get()
                        ->map(fn (RoomAccessEntry $entry) => $entry->character?->public_handle)
                        ->filter()
                        ->sort()
                        ->implode(', '))
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('blacklist')
                    ->state(fn ($record) => $record->roomAccessEntries()
                        ->where('type', RoomAccessEntry::TYPE_BLACKLIST)
                        ->with('character:id,name')
                        ->get()
                        ->map(fn (RoomAccessEntry $entry) => $entry->character?->public_handle)
                        ->filter()
                        ->sort()
                        ->implode(', '))
                    ->placeholder('-')
                    ->columnSpanFull(),
            ]);
    }
}
