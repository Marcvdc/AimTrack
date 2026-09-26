<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

// #162: de design-handoff is een export uit Claude Design. De copy in de app is
// eruit overgenomen, dus komen de onware claims hier terug (bij een nieuwe export
// of een kopie naar een andere map), dan vallen ze hier op voordat ze opnieuw in
// het product belanden. De chat-transcripten blijven buiten schot: dat is een
// verslag van destijds.

dataset('onware handoff-claims', [
    'WM-4' => ['/WM-?4/i'],
    'conformiteit' => ['/\b(wet-)?conform(e)?\b/i'],
    'klaar voor inlevering' => ['/klaar voor inlevering/i'],
    'verified' => ['/verified/i'],
    'data verlaat de server niet' => ['/verlaat de server/i'],
    'alles draait lokaal' => ['/alles draait lokaal/i'],
    'alleen de AI-coach' => ['/alleen de AI-coach stuurt/i'],
    'NL-cloud' => ['/NL-cloud/i'],
    'keuringsbrief' => ['/keuringsbrief/i'],
]);

test('de handoff-bestanden bevatten de gecorrigeerde claim niet meer', function (string $pattern): void {
    $files = Finder::create()
        ->files()
        ->in(base_path('.ai/design-handoff'))
        ->exclude('chats')
        ->name(['*.jsx', '*.html', '*.js']);

    expect(iterator_count($files))->toBeGreaterThan(0);

    $hits = [];

    foreach ($files as $file) {
        foreach (preg_split('/\R/', $file->getContents()) as $number => $line) {
            if (preg_match($pattern, $line) === 1) {
                $hits[] = $file->getRelativePathname().':'.($number + 1);
            }
        }
    }

    expect($hits)->toBe([]);
})->with('onware handoff-claims');
