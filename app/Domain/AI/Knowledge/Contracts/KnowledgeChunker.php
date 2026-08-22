<?php
namespace App\Domain\AI\Knowledge\Contracts;use App\Domain\AI\Knowledge\Data\KnowledgeContentData;
interface KnowledgeChunker{public function chunks(KnowledgeContentData$content):array;}
