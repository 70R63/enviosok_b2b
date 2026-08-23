<?php
namespace App\Domain\AI\Knowledge\Data;
final readonly class KnowledgeSearchMatch{public function __construct(public string$sourceUuid,public string$sourceName,public int$versionNumber,public string$sourceType,public string$locator,public string$excerpt,public float$score,public int$sequence,public ?int$chunkId=null){}}
