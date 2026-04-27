<?php

namespace App\Filament\Resources\MessageReports\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MessageReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('message.body')
                    ->label('Reported Message')
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('reason')
                    ->label('Report Reason')
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('message.room.name')
                    ->label('Room')
                    ->placeholder('—'),
                TextEntry::make('message.character.name')
                    ->label('Character')
                    ->placeholder('—'),
                TextEntry::make('message.user.name')
                    ->label('Accused User')
                    ->placeholder('—'),
                TextEntry::make('reporter.name')
                    ->label('Reporter')
                    ->placeholder('—'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('reviewer.name')
                    ->label('Reviewed By')
                    ->placeholder('—'),
                TextEntry::make('reviewed_at')
                    ->label('Reviewed At')
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('created_at')
                    ->label('Reported At')
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('—'),
            ]);
    }
}
