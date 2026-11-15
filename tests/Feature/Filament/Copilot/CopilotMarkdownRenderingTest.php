<?php

declare(strict_types=1);

function renderAssistantMessage(string $content): string
{
    return view('filament-copilot::components.chat-message', [
        'msg' => ['role' => 'assistant', 'content' => $content],
    ])->render();
}

it('rendert markdown in een copilot-antwoord als html en escapet ruwe html uit de modeloutput', function (): void {
    $html = renderAssistantMessage(implode("\n", [
        'Je groepering is **stabiel**.',
        '',
        '| Serie | Score |',
        '| ----- | ----: |',
        '| 1     | 96    |',
        '',
        '<script>alert(1)</script>',
    ]));

    expect($html)
        ->toContain('<strong>stabiel</strong>')
        ->toContain('<table>')
        ->toContain('<td align="right">96</td>')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->not->toContain('<script>alert(1)</script>');
});

it('parset een lange alinea in een copilot-antwoord in lineaire tijd', function (): void {
    $measure = function (int $lines): float {
        $content = str_repeat('1'.str_repeat('a', 199)."\n", $lines);
        $start = hrtime(true);
        renderAssistantMessage($content);

        return (hrtime(true) - $start) / 1e9;
    };

    $measure(1000);
    $short = $measure(10000);
    $long = $measure(40000);

    expect($long / $short)->toBeLessThan(8.0);
});
