<?php
namespace App\Domain\AI\Actions\Contracts;
use App\Domain\AI\Actions\Data\{ActionExecutionContext,ActionResultData};
interface ActionHandler{public function execute(ActionExecutionContext $context,array $arguments):ActionResultData;}
