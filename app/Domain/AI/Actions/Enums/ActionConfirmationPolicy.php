<?php

namespace App\Domain\AI\Actions\Enums;

enum ActionConfirmationPolicy: string
{
    case None = 'none';
    case Required = 'required';
}
