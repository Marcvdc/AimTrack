<?php

use Mockery\MockInterface;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Vervangt de Sentry-hub door een mock die alles negeert behalve wat de test
 * verwacht. Integration::handles() meldt via SentrySdk::getCurrentHub(), dus
 * die wordt ook omgezet.
 */
function fakeSentryHub(): MockInterface
{
    $hub = Mockery::mock(HubInterface::class)->shouldIgnoreMissing();

    SentrySdk::setCurrentHub($hub);
    app()->instance('sentry', $hub);

    return $hub;
}
