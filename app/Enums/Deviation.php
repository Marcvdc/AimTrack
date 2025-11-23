<?php

namespace App\Enums;

enum Deviation: string
{
    case LEFT = 'left';
    case RIGHT = 'right';
    case HIGH = 'high';
    case LOW = 'low';
    case NONE = 'none';
}
