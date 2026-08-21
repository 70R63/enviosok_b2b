<?php
namespace App\Domain\AI\Agents\Enums;
enum AgentStatus:string { case Draft='draft'; case Active='active'; case Paused='paused'; case Retired='retired'; }
