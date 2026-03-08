<?php

namespace App\Filament\Resources\MessageReports;

use App\Filament\Resources\MessageReports\Pages\EditMessageReport;
use App\Filament\Resources\MessageReports\Pages\ListMessageReports;
use App\Filament\Resources\MessageReports\Pages\ViewMessageReport;
use App\Filament\Resources\MessageReports\Schemas\MessageReportForm;
use App\Filament\Resources\MessageReports\Schemas\MessageReportInfolist;
use App\Filament\Resources\MessageReports\Tables\MessageReportsTable;
use App\Models\MessageReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MessageReportResource extends Resource
{
    protected static ?string $model = MessageReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Message Reports';

    protected static ?string $pluralModelLabel = 'Message Reports';

    protected static ?string $modelLabel = 'Message Report';

    public static function form(Schema $schema): Schema
    {
        return MessageReportForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MessageReportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MessageReportsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMessageReports::route('/'),
            'view' => ViewMessageReport::route('/{record}'),
            'edit' => EditMessageReport::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}