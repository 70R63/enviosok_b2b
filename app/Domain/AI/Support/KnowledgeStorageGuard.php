<?php
namespace App\Domain\AI\Support;
use App\Domain\AI\Support\Exceptions\InvalidKnowledgeDiskException;
final class KnowledgeStorageGuard
{
    public function disk(): string
    {
        $disk = trim((string) config('ai.knowledge.disk', 'local'));
        $diskConfig = config("filesystems.disks.{$disk}");
        if ($disk === '' || ! is_array($diskConfig)) throw new InvalidKnowledgeDiskException('The AI knowledge disk is not configured.');
        if ($disk === 'public' || ($diskConfig['visibility'] ?? null) === 'public') throw new InvalidKnowledgeDiskException('AI knowledge must use a private filesystem disk.');
        return $disk;
    }
}
