<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App;

class RastreoRedpack extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rastreo:redpack';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando para realizar el rastro automatico para RedPack';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $controller = App::make('\App\Http\Controllers\RastreosController');
        app()->call([$controller, 'redpackAutomatico'], []);

        return Command::SUCCESS;
    }
}
