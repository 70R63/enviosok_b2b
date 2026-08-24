<?php
namespace App\Domain\AI\Actions\Contracts;
use App\Domain\AI\Actions\Models\ActionRun;
interface ActionConfirmationPresenter
{
    /** @return array{title:string,fields:list<array{label:string,value:string}>} */
    public function present(ActionRun $action): array;
}
