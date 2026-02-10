public function panel(Panel $panel): Panel
{
    return $panel
        ->default()              // 👈 THIS LINE
        ->id('admin')
        ->path('panopticon')     // 👈 THIS LINE
        ->resources([
            // …
        ])
        ->widgets([
            // …
        ]);
}
