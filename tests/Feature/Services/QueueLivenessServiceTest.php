<?php

use App\Services\QueueLivenessService;
use Illuminate\Support\Facades\DB;

function livenessJob(array $attributes = []): void
{
    DB::table('jobs')->insert($attributes + [
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->getTimestamp(),
        'created_at' => now()->getTimestamp(),
    ]);
}

beforeEach(function () {
    $this->travelTo(now()->startOfSecond());
    config([
        'queue.default' => 'database',
        'queue.connections.database.retry_after' => 90,
    ]);
    $this->service = app(QueueLivenessService::class);
});

test('an empty queue has no age', function () {
    expect($this->service->oldestRunnableJobAgeInSeconds())->toBeNull();
});

test('the age is measured from available_at', function () {
    livenessJob(['available_at' => now()->subSeconds(120)->getTimestamp()]);

    expect($this->service->oldestRunnableJobAgeInSeconds())->toBe(120);
});

test('the oldest job decides', function () {
    livenessJob(['available_at' => now()->subSeconds(1000)->getTimestamp()]);
    livenessJob(['available_at' => now()->subSeconds(1)->getTimestamp()]);

    expect($this->service->oldestRunnableJobAgeInSeconds())->toBe(1000);
});

test('a delayed job does not count', function () {
    livenessJob([
        'available_at' => now()->addHour()->getTimestamp(),
        'created_at' => now()->subDay()->getTimestamp(),
    ]);

    expect($this->service->oldestRunnableJobAgeInSeconds())->toBeNull();
});

test('a job a worker is processing does not count', function () {
    livenessJob([
        'available_at' => now()->subSeconds(600)->getTimestamp(),
        'reserved_at' => now()->subSeconds(5)->getTimestamp(),
    ]);

    expect($this->service->oldestRunnableJobAgeInSeconds())->toBeNull();
});

test('an expired reservation counts from reserved_at plus retry_after', function () {
    livenessJob([
        'available_at' => now()->subDay()->getTimestamp(),
        'reserved_at' => now()->subSeconds(350)->getTimestamp(),
    ]);

    expect($this->service->oldestRunnableJobAgeInSeconds())->toBe(260);
});

test('jobs on any queue name count', function () {
    livenessJob(['queue' => 'anders', 'available_at' => now()->subSeconds(600)->getTimestamp()]);

    expect($this->service->oldestRunnableJobAgeInSeconds())->toBe(600);
});

test('a driver other than database gives no age', function () {
    livenessJob(['available_at' => now()->subSeconds(600)->getTimestamp()]);
    config(['queue.default' => 'sync']);

    expect($this->service->oldestRunnableJobAgeInSeconds())->toBeNull();
});
