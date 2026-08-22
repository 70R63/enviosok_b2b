<?php
namespace App\Domain\AI\Runtime\Enums;enum RuntimeRunStatus:string{case Started='started';case Completed='completed';case Failed='failed';case SkippedNoKnowledge='skipped_no_knowledge';}
