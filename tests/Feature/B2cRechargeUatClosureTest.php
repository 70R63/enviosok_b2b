<?php

namespace Tests\Feature;

use App\Http\Controllers\B2cMisEnviosController;
use App\Models\B2cRecarga;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

final class B2cRechargeUatClosureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('b2c_recargas', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('user_id'); $t->decimal('monto', 10, 2); $t->string('estatus');
            $t->string('mp_preference_id')->nullable(); $t->string('mp_payment_id')->nullable();
            $t->string('mp_status')->nullable(); $t->string('referencia'); $t->timestamps();
        });
        Schema::create('b2c_saldos', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('user_id')->unique(); $t->decimal('saldo', 10, 2); $t->timestamps();
        });
        Schema::create('b2c_movimientos_saldo', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('user_id'); $t->string('tipo'); $t->decimal('monto', 10, 2);
            $t->decimal('saldo_anterior', 10, 2); $t->decimal('saldo_nuevo', 10, 2);
            $t->string('referencia'); $t->string('estatus'); $t->timestamps();
        });
    }

    public function test_duplicate_approval_credits_balance_once(): void
    {
        $recarga = B2cRecarga::create(['user_id'=>9,'monto'=>300,'estatus'=>'PENDIENTE','referencia'=>'RECARGA-1']);
        $method = new ReflectionMethod(B2cMisEnviosController::class, 'aplicarRecargaSaldo');
        $method->setAccessible(true);
        $controller = app(B2cMisEnviosController::class);
        $method->invoke($controller, $recarga, 'payment-1', 'approved');
        $method->invoke($controller, $recarga, 'payment-1', 'approved');
        $this->assertSame(300.0, (float) DB::table('b2c_saldos')->value('saldo'));
        $this->assertSame(1, DB::table('b2c_movimientos_saldo')->count());
    }

    public function test_controller_uses_cached_config_and_contains_no_debug_dump(): void
    {
        $source = (string) file_get_contents(app_path('Http/Controllers/B2cMisEnviosController.php'));
        $this->assertStringContainsString("config('services.mercadopago.access_token')", $source);
        $this->assertStringNotContainsString("env('MERCADOPAGO_ACCESS_TOKEN')", $source);
        $this->assertStringNotContainsString('dd(', $source);
        $this->assertStringNotContainsString('getTraceAsString', $source);
    }
}
