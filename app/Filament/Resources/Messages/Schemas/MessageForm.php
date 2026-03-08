<?php

namespace App\Filament\Resources\Messages\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class MessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('room_id')
                    ->required()
                    ->numeric(),
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('character_id')
                    ->numeric(),
                Textarea::make('body')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('deleted_by')
                    ->numeric(),
            ]);
    }
}
