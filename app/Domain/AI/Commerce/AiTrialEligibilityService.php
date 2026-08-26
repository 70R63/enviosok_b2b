<?php
namespace App\Domain\AI\Commerce;
use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
final class AiTrialEligibilityService
{
 public function canStart(string $email,string $purchaseKey):bool
 {
  return !SaasOnboardingApplication::query()->where('contact_email',strtolower(trim($email)))->where('purchase_key','like','ai-trial:%')->where('purchase_key','!=',$purchaseKey)->exists();
 }
}
