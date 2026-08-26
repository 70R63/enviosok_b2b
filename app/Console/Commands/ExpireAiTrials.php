<?php
namespace App\Console\Commands;
use App\Domain\Network\Billing\Models\Subscription;use Illuminate\Console\Command;
final class ExpireAiTrials extends Command {protected $signature='ai:expire-trials';protected $description='Expire AI trials without deleting tenant data';public function handle():int{$count=Subscription::whereIn('status',['trial','trialing'])->where('trial_ends_at','<=',now())->update(['status'=>'suspended']);$this->info("Expired {$count} AI trial(s).");return self::SUCCESS;}}
