<?php
namespace App\Domain\AI\Actions\Services;
use App\Domain\AI\Actions\ActionRegistry;
use App\Domain\AI\Actions\Enums\{ActionConfirmationPolicy,ActionEffect};
use App\Domain\AI\Actions\Models\ActionRun;
use App\Domain\AI\Actions\Support\SafeConfirmationSummary;
final class PresentActionConfirmationService
{
 public function __construct(private ActionRegistry$registry,private SafeConfirmationSummary$guard){}
 public function present(ActionRun$action):array{$definition=$this->registry->find($action->action_key);if(!$definition||$definition->effect!==ActionEffect::Write||$definition->confirmation!==ActionConfirmationPolicy::Required||!$definition->confirmationPresenter)throw new \DomainException('confirmation_unavailable');return$this->guard->validate($definition->confirmationPresenter->present($action));}
}
