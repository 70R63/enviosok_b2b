<?php
namespace App\Domain\AI\Usage\Exceptions;
final class AiCapacityExceededException extends \DomainException{public function __construct(public readonly string$capability){parent::__construct('The AI capacity limit has been reached.');}}
