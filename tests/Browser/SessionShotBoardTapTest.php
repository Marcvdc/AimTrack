<?php

use App\Filament\Resources\SessionResource;
use App\Models\Session;
use App\Models\SessionShot;
use App\Models\User;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Playwright\Locator;

/**
 * De pagina waar het schotenbord op staat, voor een ingelogde eigenaar van de sessie.
 */
function shotBoardUrl(Session $session): string
{
    return SessionResource::getUrl('shots', ['record' => $session], isAbsolute: false);
}

/**
 * Wacht tot het bord precies $count markers van Livewire heeft gekregen en getekend.
 *
 * Page::waitForFunction() van de plugin (v4.3.1) stuurt zijn verzoek nooit naar
 * Playwright (een generator die niet doorlopen wordt), dus het wachten gebeurt hier
 * met een belofte in de pagina. Markers komen alleen uit het antwoord van de server,
 * dus als ze er zijn, staat het schot ook in de database.
 */
function waitForMarkers(AwaitableWebpage $page, int $count): void
{
    $reached = $page->script(<<<JS
        () => new Promise((resolve) => {
            const deadline = Date.now() + 10000;
            const check = () => {
                const canvas = document.querySelector('canvas[x-ref=canvas]');

                if (canvas && window.Alpine && Alpine.\$data(canvas).currentMarkers.length === {$count}) {
                    resolve(true);
                } else if (Date.now() > deadline) {
                    resolve(false);
                } else {
                    setTimeout(check, 50);
                }
            };

            check();
        })
        JS);

    expect($reached)->toBeTrue("Het bord toonde binnen 10 seconden geen {$count} marker(s).");
}

/**
 * Het canvas van het bord met zijn grootte in CSS-pixels.
 *
 * @return array{0: Locator, 1: array{x: float, y: float, width: float, height: float}}
 */
function shotBoardCanvas(AwaitableWebpage $page): array
{
    $canvas = $page->page()->locator('canvas[x-ref=canvas]');
    $box = $canvas->boundingBox();

    expect($box)->not->toBeNull()
        ->and($box['width'])->toBeGreaterThan(100);

    return [$canvas, $box];
}

/**
 * Een nagebootste aanraking midden op het bord: pointerdown, optioneel een
 * contextmenu zoals Chrome op Android dat bij lang indrukken stuurt, en pointerup.
 * Het script geeft het aantal verstuurde events terug.
 */
function touchSequenceScript(bool $withContextMenu): string
{
    $contextMenu = $withContextMenu ? 'true' : 'false';

    return <<<JS
        () => {
            const canvas = document.querySelector('canvas[x-ref=canvas]');
            const rect = canvas.getBoundingClientRect();
            const init = {
                bubbles: true,
                cancelable: true,
                pointerId: 7,
                pointerType: 'touch',
                isPrimary: true,
                button: 0,
                clientX: rect.left + rect.width / 2,
                clientY: rect.top + rect.height / 2,
            };
            const events = ['pointerdown', ...({$contextMenu} ? ['contextmenu'] : []), 'pointerup'];

            events.forEach((type) => canvas.dispatchEvent(new PointerEvent(type, init)));

            return events.length;
        }
        JS;
}

function deleteModalIsVisible(AwaitableWebpage $page): bool
{
    return $page->page()->getByText('Weet je zeker dat je dit schot wilt verwijderen?')->isVisible();
}

it('plaatst bij twee tikken vlak naast elkaar twee schoten en opent geen verwijdermodal', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $page = visit(shotBoardUrl($session))->on()->mobile()->assertNoJavaScriptErrors();
    waitForMarkers($page, 0);

    [$canvas, $box] = shotBoardCanvas($page);
    $center = ['x' => $box['width'] / 2, 'y' => $box['height'] / 2];

    $canvas->elementHandle()->tap(['position' => $center]);
    waitForMarkers($page, 1);

    // 8 px naast het eerste schot: binnen de oude trefzone van 15 px, waar een
    // tik vroeger op de bestaande marker landde in plaats van een schot te plaatsen.
    $canvas->elementHandle()->tap(['position' => ['x' => $center['x'] + 8, 'y' => $center['y']]]);
    waitForMarkers($page, 2);

    expect($session->shots()->count())->toBe(2)
        ->and(deleteModalIsVisible($page))->toBeFalse();

    $page->assertNoJavaScriptErrors();
});

it('opent de verwijdermodal bij een rechtsklik met de muis op een marker', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);
    $shot = SessionShot::factory()->create([
        'session_id' => $session->id,
        'turn_index' => 0,
        'shot_index' => 0,
        'x_normalized' => 0.5,
        'y_normalized' => 0.5,
    ]);
    $this->actingAs($user);

    $page = visit(shotBoardUrl($session))->assertNoJavaScriptErrors();
    waitForMarkers($page, 1);

    [$canvas, $box] = shotBoardCanvas($page);

    $canvas->click([
        'button' => 'right',
        'position' => ['x' => $box['width'] / 2, 'y' => $box['height'] / 2],
    ]);

    $page->page()->getByText('Weet je zeker dat je dit schot wilt verwijderen?')->waitFor(['state' => 'visible']);

    // De tekst komt via een event uit het bord in de buitenste Alpine-scope van de modal.
    expect($page->page()->locator('#delete-shot-modal [x-text="deleteConfirmationMessage"]')->textContent())
        ->toBe('Schot uit Beurt 1 verwijderen?')
        ->and($session->shots()->count())->toBe(1);

    $page->page()->locator('#delete-shot-modal')->getByRole('button', ['name' => 'Verwijderen'])->click();
    waitForMarkers($page, 0);

    expect(SessionShot::query()->whereKey($shot->id)->exists())->toBeFalse();

    $page->assertNoJavaScriptErrors();
});

it('opent geen verwijdermodal en plaatst geen schot bij lang indrukken met een vinger op een marker', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);
    SessionShot::factory()->create([
        'session_id' => $session->id,
        'turn_index' => 0,
        'shot_index' => 0,
        'x_normalized' => 0.5,
        'y_normalized' => 0.5,
    ]);
    $this->actingAs($user);

    $page = visit(shotBoardUrl($session))->on()->mobile()->assertNoJavaScriptErrors();
    waitForMarkers($page, 1);

    shotBoardCanvas($page);

    // Chrome op Android stuurt bij lang indrukken pointerdown, dan een contextmenu
    // met pointerType 'touch', en bij loslaten een pointerup. Playwright kan geen
    // echte long-press doen, dus de reeks wordt hier als events nagebootst.
    $dispatched = $page->script(touchSequenceScript(withContextMenu: true));
    $page->wait(1);

    expect($dispatched)->toBe(3)
        ->and(deleteModalIsVisible($page))->toBeFalse()
        ->and($session->shots()->count())->toBe(1);

    // Controle dat de nagebootste events de handlers wel bereiken: dezelfde reeks
    // zonder contextmenu is een gewone tik en plaatst een schot.
    expect($page->script(touchSequenceScript(withContextMenu: false)))->toBe(2);
    waitForMarkers($page, 2);

    expect($session->shots()->count())->toBe(2)
        ->and(deleteModalIsVisible($page))->toBeFalse();

    $page->assertNoJavaScriptErrors();
});
