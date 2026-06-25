<?php

namespace App\Filament\Resources\SiteContents\Tables;

use App\Models\SiteContent;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SiteContentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('collection')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('title')
                    ->description(fn (SiteContent $record): string => $record->slug)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('collection')
                    ->label('Document Group')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => SiteContent::collectionLabel($state))
                    ->sortable(),
                IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('collection')
                    ->label('Document Group')
                    ->options(SiteContent::collectionOptions()),
                SelectFilter::make('is_published')
                    ->label('Publication State')
                    ->options([
                        '1' => 'Published',
                        '0' => 'Draft',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->visible(fn (SiteContent $record): bool => ! $record->is_published)
                    ->requiresConfirmation()
                    ->action(function (SiteContent $record): void {
                        $record->forceFill(['is_published' => true])->save();
                    }),
                Action::make('unpublish')
                    ->label('Unpublish')
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->visible(fn (SiteContent $record): bool => $record->is_published)
                    ->requiresConfirmation()
                    ->action(function (SiteContent $record): void {
                        $record->forceFill(['is_published' => false])->save();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
