<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App;
use Log;
class rastreoAutomatico extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rastreo:automatico {--paridad= : Valor de para o impar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Se actualiza el rasreo';

    /**
     * Execute the console command.
     *
     * @return int
     */ 
    public function handle()
    {
        Log::info($this->option('paridad'));
        $paridad = $this->option('paridad');

        $controller = App::make('\App\Http\Controllers\API\GuiaController');
        app()->call([$controller, 'rastreoActualizarAutomatico'], ["paridad"=>$paridad]);

        return Command::SUCCESS;
    }
}
