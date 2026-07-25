<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'api_products',
            function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->boolean('active')->default(true);
                $table->boolean('billable')->default(true);
                $table->unsignedSmallInteger('sort_order')
                    ->default(0);
                $table->timestamps();
            }
        );

        Schema::create(
            'api_client_products',
            function (Blueprint $table) {
                $table->id();
                $table->foreignId('api_client_id')
                    ->constrained('api_clients')
                    ->cascadeOnDelete();
                $table->foreignId('api_product_id')
                    ->constrained('api_products')
                    ->cascadeOnDelete();
                $table->boolean('active')->default(false);
                $table->unsignedInteger('monthly_limit')
                    ->nullable();
                $table->timestamps();

                $table->unique([
                    'api_client_id',
                    'api_product_id',
                ]);
            }
        );

        Schema::table(
            'api_usage_logs',
            function (Blueprint $table) {
                $table->foreignId('api_product_id')
                    ->nullable()
                    ->after('api_key_id')
                    ->constrained('api_products')
                    ->nullOnDelete();
            }
        );

        $now = now();

        DB::table('api_products')->insert([
            [
                'code' => 'POSTAL_CODES',
                'name' => 'Códigos postales',
                'description' =>
                    'Consulta de código postal, estado, municipio y colonias.',
                'active' => true,
                'billable' => true,
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'SHIPPING_QUOTES',
                'name' => 'Cotizaciones de envío',
                'description' =>
                    'Obtención de tarifas y servicios disponibles por paquetería.',
                'active' => true,
                'billable' => true,
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'SHIPPING_LABELS',
                'name' => 'Generación de guías',
                'description' =>
                    'Creación de etiquetas y guías de envío.',
                'active' => true,
                'billable' => true,
                'sort_order' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'TRACKING',
                'name' => 'Rastreo',
                'description' =>
                    'Consulta y actualización del estado de los envíos.',
                'active' => true,
                'billable' => true,
                'sort_order' => 40,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'BILLING',
                'name' => 'Facturación',
                'description' =>
                    'Solicitud, consulta y descarga de CFDI mediante API Hub.',
                'active' => true,
                'billable' => true,
                'sort_order' => 50,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'WEBHOOKS',
                'name' => 'Webhooks',
                'description' =>
                    'Notificaciones de cambios de estado hacia sistemas externos.',
                'active' => true,
                'billable' => false,
                'sort_order' => 60,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $postalCodesProductId = DB::table(
            'api_products'
        )
            ->where('code', 'POSTAL_CODES')
            ->value('id');

        if ($postalCodesProductId) {
            DB::table('api_clients')
                ->orderBy('id')
                ->get([
                    'id',
                    'monthly_limit',
                ])
                ->each(function ($client) use (
                    $postalCodesProductId,
                    $now
                ) {
                    DB::table(
                        'api_client_products'
                    )->insertOrIgnore([
                        'api_client_id' => $client->id,
                        'api_product_id' =>
                            $postalCodesProductId,
                        'active' => true,
                        'monthly_limit' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::table(
            'api_usage_logs',
            function (Blueprint $table) {
                $table->dropConstrainedForeignId(
                    'api_product_id'
                );
            }
        );

        Schema::dropIfExists(
            'api_client_products'
        );

        Schema::dropIfExists('api_products');
    }
};
