<?php

namespace App\Filament\Resources\Characters\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CharacterForm
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
                TextInput::make('avatar'),
                TextInput::make('slug')
                    ->required(),
                Textarea::make('profile_html')
                    ->columnSpanFull(),
                Textarea::make('settings')
                    ->columnSpanFull(),
                TextInput::make('text_color_1')
                    ->required()
                    ->default('#D8F3FF'),
                TextInput::make('text_color_2'),
                TextInput::make('text_color_3'),
                TextInput::make('text_color_4'),
                Toggle::make('fade_message')
                    ->required(),
                Toggle::make('fade_name')
                    ->required(),
                TextInput::make('name_font'),
                TextInput::make('name_effect'),
            ]);
    }
}
