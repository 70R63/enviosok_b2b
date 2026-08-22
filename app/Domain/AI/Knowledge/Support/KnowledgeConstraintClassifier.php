<?php
namespace App\Domain\AI\Knowledge\Support;
use Illuminate\Database\QueryException;
final class KnowledgeConstraintClassifier
{
    public static function isRevisionNumberCollision(QueryException $e):bool{return self::isUnique($e)&&self::mentions($e,['ai_knowledge_versions_number_uq','ai_knowledge_source_versions.tenant_id, ai_knowledge_source_versions.knowledge_source_id, ai_knowledge_source_versions.version_number']);}
    public static function isAttachmentSourceCollision(QueryException $e):bool{return self::isUnique($e)&&self::mentions($e,['ai_agent_knowledge_source_uq','ai_agent_version_knowledge_sources.tenant_id, ai_agent_version_knowledge_sources.agent_version_id, ai_agent_version_knowledge_sources.knowledge_source_id']);}
    private static function isUnique(QueryException$e):bool{$state=(string)$e->getCode();$info=$e->errorInfo??[];$driver=(int)($info[1]??0);return in_array($state,['23000','23505'],true)||in_array($driver,[19,1062,2067],true);}
    private static function mentions(QueryException$e,array$needles):bool{$message=strtolower($e->getMessage());foreach($needles as$needle)if(str_contains($message,strtolower($needle)))return true;return false;}
}
