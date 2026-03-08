<?php

namespace App\Filament\Resources\MessageReports\Pages;

use App\Filament\Resources\MessageReports\MessageReportResource;
use Filament\Resources\Pages\ListRecords;

class ListMessageReports extends ListRecords
{
    protected static string $resource = MessageReportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}