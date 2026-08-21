<?php

namespace App\Domain\Network\ProductShell;

enum TenantWorkspace: string
{
    case ZigoPlatform = 'zigo_platform';
    case ZigoAi = 'zigo_ai';
}
