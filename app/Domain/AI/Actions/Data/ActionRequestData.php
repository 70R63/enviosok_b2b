<?php
namespace App\Domain\AI\Actions\Data;
final readonly class ActionRequestData{public function __construct(public string $actionKey,public array $arguments){if(!preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D',$actionKey)||array_is_list($arguments))throw new \InvalidArgumentException('Invalid Action request.');}}
