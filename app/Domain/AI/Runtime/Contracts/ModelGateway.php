<?php
namespace App\Domain\AI\Runtime\Contracts;
use App\Domain\AI\Runtime\Data\{ModelRequestData,ModelResponseData};
interface ModelGateway { public function generate(ModelRequestData $request): ModelResponseData; }
