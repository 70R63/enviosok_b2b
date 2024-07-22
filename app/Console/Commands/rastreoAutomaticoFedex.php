<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App;

class rastreoAutomaticoFedex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rastreo:fedex';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'rastreo automatico para la mensajeria FEDEX';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $controller = App::make('\App\Http\Controllers\API\GuiaController');
        app()->call([$controller, 'rastreoAutomaticoFedex'], []);
        return Command::SUCCESS;
    }
}
