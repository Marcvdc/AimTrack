<?php

use App\Models\User;
use Illuminate\Support\Facades\File;

/**
 * Bewaakt dat AimTrack zijn webfonts zelf serveert (#128). Elk verzoek naar
 * een externe font-host stuurt het IP-adres van de schutter mee naar een
 * derde partij; de landing en het panel mogen daar dus niets van laden.
 */
dataset('externe font-hosts', [
    'google css' => 'fonts.googleapis.com',
    'google bestanden' => 'fonts.gstatic.com',
    'bunny' => 'fonts.bunny.net',
]);

it('laadt op de landingspagina geen fonts van een externe host', function (string $host) {
    $this->get('/')
        ->assertOk()
        ->assertDontSee($host, escape: false);
})->with('externe font-hosts');

it('laadt op de loginpagina van het panel geen fonts van een externe host', function (string $host) {
    $this->get('/admin/login')
        ->assertOk()
        ->assertDontSee($host, escape: false);
})->with('externe font-hosts');

it('laadt op een panelpagina met ingelogde gebruiker geen fonts van een externe host', function (string $host) {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertOk()
        ->assertDontSee($host, escape: false);
})->with('externe font-hosts');

it('linkt op landing en panel de lokale font-stylesheet', function (string $path) {
    $this->actingAs(User::factory()->create())
        ->get($path)
        ->assertOk()
        ->assertSee(asset('webfonts/aimtrack-fonts.css'), escape: false);
})->with(['landing' => '/', 'panel' => '/admin']);

it('levert voor elke @font-face een bestaand fontbestand onder public/webfonts', function () {
    $css = (string) file_get_contents(public_path('webfonts/aimtrack-fonts.css'));

    preg_match_all('/url\(\.\/([^)]+)\)/', $css, $matches);

    expect($matches[1])->toHaveCount(13);

    foreach ($matches[1] as $file) {
        expect(public_path('webfonts/'.$file))->toBeFile();
    }

    expect($css)
        ->toContain("font-family: 'Inter';")
        ->toContain("font-family: 'JetBrains Mono';")
        ->toContain('font-display: swap;')
        ->not->toContain('http');
});

it('levert de OFL-licentie mee bij beide fonts', function (string $font) {
    expect((string) file_get_contents(public_path("webfonts/{$font}/OFL.txt")))
        ->toContain('SIL Open Font License, Version 1.1');
})->with(['inter', 'jetbrains-mono']);

it('bevat in de views en de gebuilde bronnen geen verwijzing naar een externe font-host', function (string $host) {
    $files = collect([
        ...File::allFiles(resource_path('views')),
        ...File::allFiles(resource_path('css')),
        ...File::allFiles(app_path()),
    ])
        ->map(fn (SplFileInfo $file): string => $file->getPathname())
        ->push(public_path('webfonts/aimtrack-fonts.css'));

    expect($files)->not->toBeEmpty();

    $hits = $files->filter(fn (string $file): bool => str_contains((string) file_get_contents($file), $host));

    expect($hits->values()->all())->toBe([]);
})->with('externe font-hosts');
