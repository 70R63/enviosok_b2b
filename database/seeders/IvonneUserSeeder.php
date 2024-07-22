<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class IvonneUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
         User::create([
            'name' => 'Ivonne Rodríguez',
            'email' => 'irodriguez@gmail.com',
            'password' => bcrypt('Aq1zsw2x.'),
        ]);
    }
}
