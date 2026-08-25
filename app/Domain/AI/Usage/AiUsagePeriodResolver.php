<?php
namespace App\Domain\AI\Usage;
use App\Domain\Network\Billing\Models\Subscription;use Carbon\CarbonImmutable;
final class AiUsagePeriodResolver{
 public function current(Subscription$s,?CarbonImmutable$at=null):?AiUsagePeriod{$at??=CarbonImmutable::now(config('app.timezone'));$anchor=CarbonImmutable::instance($s->current_period_start)->setTimezone(config('app.timezone'));$subscriptionEnd=CarbonImmutable::instance($s->current_period_end)->setTimezone(config('app.timezone'));if($at->lt($anchor)||$at->gte($subscriptionEnd))return null;$month=0;do{$start=$anchor->addMonthsNoOverflow($month);$next=$anchor->addMonthsNoOverflow($month+1);$month++;}while($next->lte($at)&&$next->lt($subscriptionEnd));return new AiUsagePeriod($start,$next->min($subscriptionEnd));}
}
