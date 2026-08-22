<?php
namespace App\Domain\AI\Knowledge\Data;
final readonly class KnowledgeSourceName
{
    private function __construct(public string $value) {}
    public static function from(mixed $value): self
    {
        if (! is_string($value)) throw new \InvalidArgumentException('Knowledge source name is invalid.');
        $value=trim($value);$decoded=$value;for($i=0;$i<2;$i++)$decoded=html_entity_decode($decoded,ENT_QUOTES|ENT_HTML5,'UTF-8');
        if(mb_strlen($value)<1||mb_strlen($value)>160||preg_match('/[\x00-\x1F\x7F]/u',$value)||preg_match('/<[^>]*>/u',$decoded))throw new \InvalidArgumentException('Knowledge source name is invalid.');
        if(preg_match('/(?:\bbearer\s+[A-Za-z0-9._~+\/-]{12,}|\b(?:access[ _-]?token|client[ _-]?secret|api[ _-]?key|password)\s*[:=]\s*\S+|-----BEGIN\s+(?:RSA\s+)?PRIVATE\s+KEY-----)/i',$decoded))throw new \InvalidArgumentException('Knowledge source name is invalid.');
        if(preg_match('/\b(?:system|developer)\s+prompt\s*[:=]|\bignore\s+(?:all\s+)?previous\s+instructions\b|\bact\s+as\s+(?:the\s+)?system\b/i',$decoded))throw new \InvalidArgumentException('Knowledge source name is invalid.');
        return new self($value);
    }
}
