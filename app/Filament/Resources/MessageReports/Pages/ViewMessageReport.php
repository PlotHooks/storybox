<?php

namespace App\Filament\Resources\MessageReports\Pages;

use App\Filament\Resources\MessageReports\MessageReportResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMessageReport extends ViewRecord
{
    protected static string $resource = MessageReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
