<?php
namespace App\Http\Requests\Tenant;use App\Domain\AI\Knowledge\Data\KnowledgeSourceName;final class StoreKnowledgeSourceRequest extends KnowledgeContentRequest{protected function includesName():bool{return true;}public function sourceName():KnowledgeSourceName{return KnowledgeSourceName::from($this->validated('name'));}}
