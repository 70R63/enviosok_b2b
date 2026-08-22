<?php
namespace App\Domain\AI\Knowledge\Contracts;use App\Domain\AI\Agents\Models\AgentVersion;use App\Domain\AI\Knowledge\Data\KnowledgeSearchQuery;
interface KnowledgeRetriever{public function retrieve(AgentVersion$agentVersion,KnowledgeSearchQuery$query):array;}
