<?php

namespace App\Filament\Resources\SiteContents;

use App\Filament\Resources\SiteContents\Pages\ManageSiteContent;
use App\Models\SiteContent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;

class SiteContentResource extends Resource
{
    protected static ?string $model = SiteContent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Site Content';

    protected static ?string $modelLabel = 'Site Content';

    protected static ?string $pluralModelLabel = 'Site Content';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSiteContent::route('/'),
        ];
    }
}
