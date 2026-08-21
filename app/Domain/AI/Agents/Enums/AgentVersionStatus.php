<?php
namespace App\Domain\AI\Agents\Enums;
enum AgentVersionStatus:string { case Draft='draft'; case Testing='testing'; case Approved='approved'; case Published='published'; case Retired='retired'; }
