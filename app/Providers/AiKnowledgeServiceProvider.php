<?php
namespace App\Providers;use App\Domain\AI\Knowledge\Chunking\DeterministicKnowledgeChunker;use App\Domain\AI\Knowledge\Contracts\{KnowledgeChunker,KnowledgeRetriever};use App\Domain\AI\Knowledge\Retrieval\LexicalKnowledgeRetriever;use Illuminate\Support\ServiceProvider;
final class AiKnowledgeServiceProvider extends ServiceProvider{public function register():void{$this->app->bind(KnowledgeChunker::class,DeterministicKnowledgeChunker::class);$this->app->bind(KnowledgeRetriever::class,LexicalKnowledgeRetriever::class);}}
