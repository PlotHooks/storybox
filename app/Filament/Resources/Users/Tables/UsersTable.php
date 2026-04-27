<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),

                IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean(),

                IconColumn::make('is_banned')
                    ->label('Banned')
                    ->boolean(),

                TextColumn::make('banned_until')
                    ->label('Banned Until')
                    ->dateTime('M j, Y g:i A')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),

                EditAction::make(),

                Action::make('ban')
                    ->label('Ban')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn ($record): bool => ! (bool) $record->is_banned)
                    ->form([
                        Select::make('duration')
                            ->label('Ban Length')
                            ->options([
                                '1_hour' => '1 hour',
                                '24_hours' => '24 hours',
                                '7_days' => '7 days',
                                '30_days' => '30 days',
                                'permanent' => 'Permanent',
                            ])
                            ->required()
                            ->default('7_days'),

                        Textarea::make('reason')
                            ->label('Reason')
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->requiresConfirmation()
                    ->action(function (array $data, $record): void {
                        $bannedUntil = match ($data['duration']) {
                            '1_hour' => Carbon::now()->addHour(),
                            '24_hours' => Carbon::now()->addDay(),
                            '7_days' => Carbon::now()->addDays(7),
                            '30_days' => Carbon::now()->addDays(30),
                            'permanent' => null,
                            default => Carbon::now()->addDays(7),
                        };

                        $record->forceFill([
                            'is_banned' => true,
                            'banned_until' => $bannedUntil,
                            'banned_reason' => $data['reason'] ?: 'Manual admin ban',
                        ])->save();
                    }),

                Action::make('unban')
                    ->label('Unban')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record): bool => (bool) $record->is_banned)
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $record->forceFill([
                            'is_banned' => false,
                            'banned_until' => null,
                            'banned_reason' => null,
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