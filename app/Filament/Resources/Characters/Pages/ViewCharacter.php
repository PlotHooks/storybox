<?php

namespace App\Filament\Resources\Characters\Pages;

use App\Filament\Resources\Characters\CharacterResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCharacter extends ViewRecord
{
    protected static string $resource = CharacterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewProfile')
                ->label('View Profile')
                ->url(fn (): string => route('characters.profile.show', $this->record), shouldOpenInNewTab: true),
            Action::make('editProfile')
                ->label('Edit Profile')
                ->url(fn (): string => route('characters.profile.edit', $this->record), shouldOpenInNewTab: true),
            Action::make('revisions')
                ->label('Revisions')
                ->url(fn (): string => route('characters.profile.revisions', $this->record), shouldOpenInNewTab: true),
            EditAction::make(),
        ];
    }
}
