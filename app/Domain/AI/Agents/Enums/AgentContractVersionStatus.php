<?php
namespace App\Domain\AI\Agents\Enums;
enum AgentContractVersionStatus:string { case Draft='draft'; case Offered='offered'; case Accepted='accepted'; case Superseded='superseded'; }
