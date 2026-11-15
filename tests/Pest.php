<?php

use Mockery\MockInterface;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/*
 * Browsertests (pest-plugin-browser) draaien tegen de echte pagina, dus met de
 * gebouwde Vite-assets (npm run build) in plaats van de withoutVite() uit de
 * TestCase. Ze vallen buiten de testsuites in phpunit.xml en draaien apart met
 * `vendor/bin/pest tests/Browser`; zie docs/AimTrack/tech/browsertests.md.
 */
uses(TestCase::class)
    ->beforeEach(fn () => $this->withVite())
    ->in('Browser');

/**
 * Vervangt de Sentry-hub door een mock. Integration::handles() meldt via
 * SentrySdk::getCurrentHub(), dus die wordt ook omgezet. Andere hub-methodes
 * (breadcrumbs, scope) worden genegeerd, maar captureException is strikt: elke
 * melding die de test niet met shouldReceive('captureException') verwacht,
 * laat de test falen.
 */
function fakeSentryHub(): MockInterface
{
    $hub = Mockery::mock(HubInterface::class)->shouldIgnoreMissing();
    $hub->shouldReceive('captureException')->never();

    SentrySdk::setCurrentHub($hub);
    app()->instance('sentry', $hub);

    return $hub;
}
