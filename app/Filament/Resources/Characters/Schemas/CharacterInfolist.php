<?php

namespace App\Filament\Resources\Characters\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CharacterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user_id')
                    ->numeric(),
                TextEntry::make('name'),
                TextEntry::make('avatar')
                    ->label('External avatar URL')
                    ->placeholder('-'),
                TextEntry::make('slug'),
                TextEntry::make('profile_html')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('settings')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('text_color_1'),
                TextEntry::make('text_color_2')
                    ->placeholder('-'),
                TextEntry::make('text_color_3')
                    ->placeholder('-'),
                TextEntry::make('text_color_4')
                    ->placeholder('-'),
                IconEntry::make('fade_message')
                    ->boolean(),
                IconEntry::make('fade_name')
                    ->boolean(),
                TextEntry::make('name_font')
                    ->placeholder('-'),
                TextEntry::make('name_effect')
                    ->placeholder('-'),
            ]);
    }
}
