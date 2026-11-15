<?php

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
