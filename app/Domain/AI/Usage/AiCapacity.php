<?php
namespace App\Domain\AI\Usage;
final readonly class AiCapacity{public function __construct(public int $used,public int $limit){}public function remaining():int{return max(0,$this->limit-$this->used);}public function reached():bool{return$this->used>=$this->limit;}}
