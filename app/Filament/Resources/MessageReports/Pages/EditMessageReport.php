<?php

namespace App\Filament\Resources\MessageReports\Pages;

use App\Filament\Resources\MessageReports\MessageReportResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMessageReport extends EditRecord
{
    protected static string $resource = MessageReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
