<?php

namespace App\Filament\Resources\SiteContents\Schemas;

use App\Models\SiteContent;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class SiteContentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Document')
                    ->description('Manage public-facing StoryBox documents. Published Rules / FAQ documents appear as tabs in the public Rules / FAQ window, ordered by sort_order.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                                if (filled($get('slug'))) {
                                    return;
                                }

                                $set('slug', Str::slug((string) $state));
                            }),
                        Select::make('collection')
                            ->label('Document Group')
                            ->options(SiteContent::collectionOptions())
                            ->default(SiteContent::COLLECTION_RULES_FAQ)
                            ->required()
                            ->native(false)
                            ->helperText('Rules / FAQ documents become public tabs in the shared room popout. Legal / Policy and Site Info are ready for future document groups.'),
                        TextInput::make('sort_order')
                            ->label('Tab Order')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->helperText('Lower numbers appear first in the public document tabs.'),
                        Toggle::make('is_published')
                            ->label('Published')
                            ->default(false)
                            ->helperText('Only published documents are visible in the public floating window.'),
                        Textarea::make('body')
                            ->required()
                            ->rows(18)
                            ->columnSpanFull()
                            ->helperText('Uses StoryBox\'s safe rich-text tags like [b], [i], [u], [s], [small], and [large].'),
                    ]),
                Section::make('Advanced')
                    ->description('These values are usually only needed for direct linking or manual cleanup.')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Auto-generated from the title by default. Adjust only when you need a custom identifier.'),
                    ]),
            ]);
    }
}
