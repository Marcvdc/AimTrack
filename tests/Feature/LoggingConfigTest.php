<?php

test('stack channel includes daily for rotation', function () {
    expect(config('logging.channels.stack.channels'))
        ->toBeArray()
        ->toContain('daily');
});

test('daily channel retains 14 days by default', function () {
    expect(config('logging.channels.daily.days'))
        ->toBe(14);
});
