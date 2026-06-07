<?php

namespace App\Filament\Resources\Characters\Tables;

use Filament\Actions\Action;
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
                TextColumn::make('profile.custom_profile_enabled')
                    ->label('Custom Profile')
                    ->formatStateUsing(fn (?bool $state): string => $state ? 'Enabled' : 'Default')
                    ->badge()
                    ->color(fn (?bool $state): string => $state ? 'warning' : 'gray'),
                IconColumn::make('profile.custom_profile_disabled_by_admin')
                    ->label('Admin Disabled')
                    ->boolean(),
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
                Action::make('viewProfile')
                    ->label('View Profile')
                    ->url(fn ($record): string => route('characters.profile.show', $record), shouldOpenInNewTab: true),
                Action::make('editProfile')
                    ->label('Edit Profile')
                    ->url(fn ($record): string => route('characters.profile.edit', $record), shouldOpenInNewTab: true),
                Action::make('revisions')
                    ->label('Revisions')
                    ->url(fn ($record): string => route('characters.profile.revisions', $record), shouldOpenInNewTab: true),
                Action::make('disableCustomProfile')
                    ->label('Disable Custom')
                    ->color('danger')
                    ->visible(fn ($record): bool => ! (bool) ($record->profile?->custom_profile_disabled_by_admin ?? false))
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $record->ensureProfile()->forceFill([
                            'custom_profile_disabled_by_admin' => true,
                        ])->save();
                    }),
                Action::make('enableCustomProfile')
                    ->label('Re-enable Custom')
                    ->color('success')
                    ->visible(fn ($record): bool => (bool) ($record->profile?->custom_profile_disabled_by_admin ?? false))
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $record->ensureProfile()->forceFill([
                            'custom_profile_disabled_by_admin' => false,
                        ])->save();
                    }),
                Action::make('revertToDefaultProfile')
                    ->label('Revert to Default')
                    ->color('warning')
                    ->visible(fn ($record): bool => (bool) ($record->profile?->custom_profile_enabled ?? false))
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $record->ensureProfile()->forceFill([
                            'custom_profile_enabled' => false,
                        ])->save();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
