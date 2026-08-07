<?php

namespace Tests\Feature;

use App\Http\Controllers\API\Payments\MercadoPagoWebhookController;
use App\Mail\GuideRecoveryMail;
use App\Models\B2cCotizacion;
use App\Models\B2cGuideRecoveryAudit;
use App\Models\B2cGuideRecoveryCase;
use App\Models\B2cGuideRecoveryToken;
use App\Services\Shipping\GuideRecoveryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

final class FinalGuideRecoveryWiringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('b2c_cotizaciones', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id')->nullable(); $t->string('referencia')->nullable();
            $t->string('remitente_email')->nullable(); $t->string('destinatario_email')->nullable();
            $t->string('payment_status')->nullable(); $t->timestamp('payment_verified_at')->nullable();
            $t->decimal('payment_verified_amount', 10, 2)->nullable(); $t->decimal('precio', 10, 2)->nullable();
            $t->timestamp('quote_expires_at')->nullable(); $t->string('provider')->nullable(); $t->string('carrier')->nullable();
            $t->string('service_code')->nullable(); $t->string('estatus')->nullable(); $t->string('guia_estatus')->nullable();
            $t->string('guia_id')->nullable(); $t->string('tracking_number')->nullable(); $t->string('documento')->nullable();
            $t->unsignedInteger('guia_generation_attempts')->default(0); $t->string('guia_last_error_code')->nullable();
            $t->timestamp('guide_processing_email_sent_at')->nullable(); $t->timestamp('guide_generated_email_sent_at')->nullable();
            $t->timestamp('guide_pending_email_sent_at')->nullable(); $t->timestamp('guide_pdf_recovered_email_sent_at')->nullable(); $t->timestamps();
        });
        include database_path('migrations/2026_08_06_130000_create_b2c_guide_recovery_tables.php');
        // The migration object is returned by include; create just the recovery tables explicitly for this isolated schema.
        Schema::create('b2c_guide_recovery_tokens', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('cotizacion_id'); $t->string('email_hash',64); $t->string('token_hash',64)->unique(); $t->unsignedSmallInteger('attempts')->default(0); $t->unsignedSmallInteger('max_attempts')->default(10); $t->timestamp('expires_at'); $t->timestamp('last_used_at')->nullable(); $t->timestamp('revoked_at')->nullable(); $t->timestamps(); });
        Schema::create('b2c_guide_recovery_cases', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('cotizacion_id')->unique(); $t->string('classification',40); $t->string('status',30)->default('OPEN'); $t->decimal('original_paid_amount',10,2)->nullable(); $t->decimal('requote_total',10,2)->nullable(); $t->timestamp('requote_reviewed_at')->nullable(); $t->unsignedBigInteger('requote_reviewed_by')->nullable(); $t->timestamps(); });
        Schema::create('b2c_guide_recovery_audits', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('cotizacion_id'); $t->unsignedBigInteger('actor_user_id')->nullable(); $t->string('action',50); $t->string('result',30); $t->string('correlation_id',100)->nullable(); $t->string('ip_hash',64)->nullable(); $t->json('metadata')->nullable(); $t->timestamps(); });
        config(['zigo_guide_recovery.token_hours' => 12, 'zigo_guide_recovery.max_attempts' => 7]);
    }

    public function test_guest_approved_provider_failure_is_wired_once_and_magic_link_works(): void
    {
        Mail::fake(); $q = $this->failedQuote(null); $service = app(GuideRecoveryService::class);
        $token = $service->recoverCreationFailure($q, 'buyer@example.test');
        $service->recoverCreationFailure($q->fresh(), 'buyer@example.test');

        $this->assertSame(1, B2cGuideRecoveryCase::count()); $this->assertSame('CREATION_FAILED', B2cGuideRecoveryCase::first()->classification);
        $this->assertSame(1, B2cGuideRecoveryToken::count()); $this->assertSame(7, B2cGuideRecoveryToken::first()->max_attempts);
        $this->assertSame(1, B2cGuideRecoveryAudit::where('action','CREATION_FAILED_RECOVERY_OPENED')->count());
        Mail::assertSent(GuideRecoveryMail::class, 1); $this->assertNotNull($service->resolve($token));
        $this->assertNull($q->fresh()->user_id); $this->assertSame('approved', $q->fresh()->payment_status);

        $method = new ReflectionMethod(MercadoPagoWebhookController::class, 'shouldGenerateXpertaGuide'); $method->setAccessible(true);
        $this->assertFalse($method->invoke(new MercadoPagoWebhookController(), $q->fresh()), 'El segundo callback no debe llamar otra vez a Xperta.');
    }

    public function test_authenticated_quote_uses_same_idempotent_recovery(): void
    {
        Mail::fake(); $q = $this->failedQuote(42); $service = app(GuideRecoveryService::class);
        $this->assertNotNull($service->recoverCreationFailure($q, 'member@example.test'));
        $service->recoverCreationFailure($q->fresh(), 'member@example.test');
        $this->assertSame(1, B2cGuideRecoveryCase::count()); $this->assertSame(1, B2cGuideRecoveryToken::count());
        Mail::assertSent(GuideRecoveryMail::class, 1); $this->assertSame(42, $q->fresh()->user_id);
    }

    private function failedQuote(?int $userId): B2cCotizacion
    {
        return B2cCotizacion::create(['user_id'=>$userId,'referencia'=>'133','remitente_email'=>'buyer@example.test','payment_status'=>'approved','payment_verified_at'=>now(),'payment_verified_amount'=>150,'precio'=>150,'provider'=>'xperta','carrier'=>'estafeta','service_code'=>'diasig','estatus'=>'ERROR_GENERACION_GUIA','guia_estatus'=>'ERROR_PROVEEDOR','guia_generation_attempts'=>1,'guia_last_error_code'=>'XPERTA_PROVIDER_ERROR']);
    }
}
