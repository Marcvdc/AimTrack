<?php

namespace App\Filament\Widgets;

use App\Models\Session;
use Filament\Widgets\Widget;
use Livewire\Attributes\Reactive;

class SessionShotBoardWidget extends Widget
{
    protected string $view = 'filament.widgets.session-shot-board-widget';

    #[Reactive]
    public ?Session $session = null;

    #[Reactive]
    public bool $readOnly = false;
}
