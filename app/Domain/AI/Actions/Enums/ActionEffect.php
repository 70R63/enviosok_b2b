<?php

namespace App\Domain\AI\Actions\Enums;

enum ActionEffect: string
{
    case Read = 'read';
    case Write = 'write';
}
