<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //Commands\RastreoGuias::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        //$schedule->command('inspire')->hourly();
        $schedule->command('rastreo:automatico')->hourly();

        $schedule
            ->command('api-hub:webhooks:deliver --limit=100')
            ->everyMinute()
            ->withoutOverlapping(10);

        $schedule
            ->command('zigo:security:cleanup-orphan-evidence --delete')
            ->dailyAt('03:20')
            ->withoutOverlapping(30);
        $schedule->command('zigo:subscriptions:billing-cycle --notify --suspend')->dailyAt('04:10')->withoutOverlapping(60);
        $schedule->command('ai:expire-trials')->dailyAt('04:20')->withoutOverlapping(30);
        $schedule->command('ai:process-billing-lifecycle')->hourly()->withoutOverlapping(30);
        
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
