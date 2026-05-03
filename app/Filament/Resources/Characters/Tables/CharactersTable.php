<?php

namespace App\Filament\Resources\Characters\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CharactersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('avatar')
                    ->label('External avatar URL')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('text_color_1')
                    ->searchable(),
                TextColumn::make('text_color_2')
                    ->searchable(),
                TextColumn::make('text_color_3')
                    ->searchable(),
                TextColumn::make('text_color_4')
                    ->searchable(),
                IconColumn::make('fade_message')
                    ->boolean(),
                IconColumn::make('fade_name')
                    ->boolean(),
                TextColumn::make('name_font')
                    ->searchable(),
                TextColumn::make('name_effect')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
