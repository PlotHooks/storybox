<?php

namespace App\Filament\Resources\MessageReports\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class MessageReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('message.id')
                    ->label('#')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('message.body')
                    ->label('Message')
                    ->limit(60)
                    ->searchable()
                    ->tooltip(fn ($record): ?string => $record->message?->body),

                TextColumn::make('message.user.name')
                    ->label('Accused User')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('reporter.name')
                    ->label('Reporter')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('reviewer.name')
                    ->label('Reviewed By')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reviewed_at')
                    ->label('Reviewed At')
                    ->since()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Reported')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'dismissed' => 'Dismissed',
                        'actioned' => 'Actioned',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('dismiss')
                    ->label('Dismiss')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => $record->status === 'open')
                    ->action(function ($record): void {
                        $record->forceFill([
                            'status' => 'dismissed',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ])->save();
                    }),

                Action::make('deleteMessage')
                    ->label('Delete Message')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool =>
                        $record->status === 'open'
                        && $record->message
                        && ! $record->message->trashed()
                    )
                    ->action(function ($record): void {
                        $record->message->delete();

                        $record->forceFill([
                            'status' => 'actioned',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ])->save();
                    }),

                Action::make('banUser')
                    ->label('Ban User')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn ($record): bool =>
                        $record->status === 'open'
                        && $record->message
                        && $record->message->user
                        && ! $record->message->user->is_banned
                    )
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
                            ->maxLength(1000)
                            ->default('Banned from message report review.'),
                    ])
                    ->requiresConfirmation()
                    ->action(function (array $data, $record): void {
                        $user = $record->message->user;

                        $bannedUntil = match ($data['duration']) {
                            '1_hour' => Carbon::now()->addHour(),
                            '24_hours' => Carbon::now()->addDay(),
                            '7_days' => Carbon::now()->addDays(7),
                            '30_days' => Carbon::now()->addDays(30),
                            'permanent' => null,
                            default => Carbon::now()->addDays(7),
                        };

                        $user->forceFill([
                            'is_banned' => true,
                            'banned_until' => $bannedUntil,
                            'banned_reason' => $data['reason'] ?: 'Banned from message report review.',
                        ])->save();

                        $record->forceFill([
                            'status' => 'actioned',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ])->save();
                    }),

                Action::make('deleteAndBan')
                    ->label('Delete + Ban')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->visible(fn ($record): bool =>
                        $record->status === 'open'
                        && $record->message
                        && $record->message->user
                        && ! $record->message->trashed()
                        && ! $record->message->user->is_banned
                    )
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
                            ->maxLength(1000)
                            ->default('Message deleted and user banned from report review.'),
                    ])
                    ->requiresConfirmation()
                    ->action(function (array $data, $record): void {
                        $user = $record->message->user;

                        $bannedUntil = match ($data['duration']) {
                            '1_hour' => Carbon::now()->addHour(),
                            '24_hours' => Carbon::now()->addDay(),
                            '7_days' => Carbon::now()->addDays(7),
                            '30_days' => Carbon::now()->addDays(30),
                            'permanent' => null,
                            default => Carbon::now()->addDays(7),
                        };

                        $record->message->delete();

                        $user->forceFill([
                            'is_banned' => true,
                            'banned_until' => $bannedUntil,
                            'banned_reason' => $data['reason'] ?: 'Message deleted and user banned from report review.',
                        ])->save();

                        $record->forceFill([
                            'status' => 'actioned',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
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