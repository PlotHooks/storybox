<?php

namespace App\Filament\Resources\Rooms\Schemas;

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
                TextInput::make('owner_character_id')
                    ->numeric(),
                TextInput::make('type')
                    ->required()
                    ->default('public'),
                Select::make('visibility')
                    ->options([
                        'public' => 'public',
                        'hidden' => 'hidden',
                    ])
                    ->required()
                    ->default('public'),
            ]);
    }
}
