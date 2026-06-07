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
                TextInput::make('avatar')
                    ->label('External avatar URL')
                    ->helperText('Use an externally hosted http or https image URL. HTTPS is preferred.')
                    ->maxLength(2048)
                    ->rules([
                        'nullable',
                        'string',
                        'max:2048',
                        'url',
                        function (string $attribute, mixed $value, \Closure $fail): void {
                            if ($value === null || $value === '') {
                                return;
                            }

                            $scheme = parse_url($value, PHP_URL_SCHEME);

                            if (! in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
                                $fail('The avatar must be an http or https URL.');
                            }
                        },
                    ]),
                TextInput::make('slug')
                    ->required(),
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
