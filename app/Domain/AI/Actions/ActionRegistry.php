<?php
namespace App\Domain\AI\Actions;
use App\Domain\AI\Actions\Data\ActionDefinition;
final class ActionRegistry{private array $definitions=[];public function register(ActionDefinition $definition):void{if(isset($this->definitions[$definition->key]))throw new \LogicException('Action key is already registered.');$this->definitions[$definition->key]=$definition;}public function find(string $key):?ActionDefinition{return $this->definitions[$key]??null;}public function all():array{return$this->definitions;}}
