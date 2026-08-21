<?php

namespace App\Domain\AI\Agents\Support;

use App\Domain\AI\Support\StructuredDataGuard;
use InvalidArgumentException;

final class AiLifecycleInputGuard
{
    private const NETWORK_KEYS=['ip','ip_address','client_ip','remote_ip','remote_address','source_ip','request_ip','forwarded_for','x_forwarded_for','network_address'];

    public function reason(string $reason): string
    {
        $reason=trim($reason);
        if($reason===''||mb_strlen($reason)>500||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$reason))throw new InvalidArgumentException('Lifecycle reason must contain 1 to 500 safe characters.');
        return $reason;
    }

    public function evidence(array $evidence): array
    {
        StructuredDataGuard::validate($evidence);
        $this->inspect($evidence);
        return $evidence;
    }

    private function inspect(array $data): void
    {
        foreach($data as$key=>$value){if(is_string($key)){ $normalized=$this->normalize($key);if(in_array($normalized,self::NETWORK_KEYS,true))throw new InvalidArgumentException('Network address evidence is not allowed.');}if(is_array($value))$this->inspect($value);elseif(is_string($value)&&filter_var($value,FILTER_VALIDATE_IP)!==false)throw new InvalidArgumentException('Network address evidence is not allowed.');}
    }

    private function normalize(string$key):string{$key=preg_replace('/(?<!^)[A-Z]/','_$0',trim($key))??$key;return trim(strtolower(preg_replace('/[-\s]+/','_',$key)??$key),'_');}
}
