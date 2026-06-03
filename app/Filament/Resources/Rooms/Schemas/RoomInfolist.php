<?php

namespace App\Filament\Resources\Rooms\Schemas;

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
                TextEntry::make('ownerCharacter.name')
                    ->label('Owner Character')
                    ->placeholder('-'),
                TextEntry::make('moderators')
                    ->state(fn ($record) => $record->roomCharacterRoles()
                        ->where('role', \App\Models\RoomCharacterRole::ROLE_MODERATOR)
                        ->join('characters', 'characters.id', '=', 'room_character_roles.character_id')
                        ->orderBy('characters.name')
                        ->pluck('characters.name')
                        ->implode(', '))
                    ->placeholder('-'),
                TextEntry::make('whitelist')
                    ->state(fn ($record) => $record->roomAccessEntries()
                        ->where('type', \App\Models\RoomAccessEntry::TYPE_WHITELIST)
                        ->join('characters', 'characters.id', '=', 'room_access_entries.character_id')
                        ->orderBy('characters.name')
                        ->pluck('characters.name')
                        ->implode(', '))
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('blacklist')
                    ->state(fn ($record) => $record->roomAccessEntries()
                        ->where('type', \App\Models\RoomAccessEntry::TYPE_BLACKLIST)
                        ->join('characters', 'characters.id', '=', 'room_access_entries.character_id')
                        ->orderBy('characters.name')
                        ->pluck('characters.name')
                        ->implode(', '))
                    ->placeholder('-')
                    ->columnSpanFull(),
            ]);
    }
}
