<?php
namespace App\Domain\AI\Launchpad\Enums;
enum LaunchpadSessionStatus:string { case Draft='draft'; case Recommended='recommended'; case Converted='converted'; case Abandoned='abandoned'; }
