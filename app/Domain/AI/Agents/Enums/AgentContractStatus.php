<?php
namespace App\Domain\AI\Agents\Enums;
enum AgentContractStatus:string { case Draft='draft'; case Active='active'; case Suspended='suspended'; case Ended='ended'; }
