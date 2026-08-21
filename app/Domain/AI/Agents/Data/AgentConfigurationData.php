<?php
namespace App\Domain\AI\Agents\Data;
use App\Domain\AI\Support\StructuredDataGuard;
use InvalidArgumentException;
final readonly class AgentConfigurationData
{
    private const SECTIONS=['identity','goals','behavior','guardrails','qualification','handoff','capabilities','metadata'];
    public function __construct(public string$schemaVersion,public array$configuration)
    {
        if(trim($schemaVersion)==='')throw new InvalidArgumentException('Agent configuration schema_version is required.');
        foreach(self::SECTIONS as$section)if(!array_key_exists($section,$configuration)||!is_array($configuration[$section]))throw new InvalidArgumentException("Agent configuration section {$section} is required and must be structured.");
        foreach(['identity','goals','behavior']as$section)if($configuration[$section]===[])throw new InvalidArgumentException("Agent configuration section {$section} cannot be empty.");
        StructuredDataGuard::validate($configuration);
    }
}
