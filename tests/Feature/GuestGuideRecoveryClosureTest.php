<?php
namespace Tests\Feature;

use App\Models\B2cCotizacion;
use App\Mail\GuideRecoveryMail;
use App\Models\B2cGuideRecoveryAudit;
use App\Models\B2cGuideRecoveryCase;
use App\Services\Shipping\GuideRecoveryService;
use App\Services\Shipping\Xperta\XpertaGuidePdfService;
use App\Services\Shipping\Xperta\XpertaProviderException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class GuestGuideRecoveryClosureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp(); config(['database.default'=>'sqlite','database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>false]]);DB::purge('sqlite');DB::reconnect('sqlite');
        Schema::create('b2c_cotizaciones',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id')->nullable();$t->string('referencia')->nullable();$t->string('remitente_email')->nullable();$t->string('destinatario_email')->nullable();$t->string('payment_status')->nullable();$t->timestamp('payment_verified_at')->nullable();$t->decimal('payment_verified_amount',10,2)->nullable();$t->decimal('precio',10,2)->nullable();$t->timestamp('quote_expires_at')->nullable();$t->string('guia_id')->nullable();$t->string('tracking_number')->nullable();$t->string('documento')->nullable();$t->string('guia_estatus')->nullable();$t->string('estatus')->nullable();$t->string('guia_last_error_code')->nullable();$t->text('guia_last_error_message')->nullable();$t->unsignedInteger('guia_generation_attempts')->default(0);$t->string('service_code')->nullable();$t->string('servicio')->nullable();$t->string('guia_label_format')->nullable();$t->timestamp('guia_recovered_at')->nullable();$t->unsignedBigInteger('guia_provider_request_number')->nullable();$t->timestamp('guide_processing_email_sent_at')->nullable();$t->timestamp('guide_generated_email_sent_at')->nullable();$t->timestamp('guide_pending_email_sent_at')->nullable();$t->timestamp('guide_pdf_recovered_email_sent_at')->nullable();$t->timestamps();});
        Schema::create('b2c_guide_recovery_cases',function(Blueprint $t){$t->id();$t->unsignedBigInteger('cotizacion_id')->unique();$t->string('classification',40);$t->string('status',30)->default('OPEN');$t->decimal('original_paid_amount',10,2)->nullable();$t->decimal('requote_total',10,2)->nullable();$t->timestamp('requote_reviewed_at')->nullable();$t->unsignedBigInteger('requote_reviewed_by')->nullable();$t->timestamps();});
        Schema::create('b2c_guide_recovery_audits',function(Blueprint $t){$t->id();$t->unsignedBigInteger('cotizacion_id');$t->unsignedBigInteger('actor_user_id')->nullable();$t->string('action',50);$t->string('result',30);$t->string('correlation_id',100)->nullable();$t->string('ip_hash',64)->nullable();$t->json('metadata')->nullable();$t->timestamps();});
        Schema::create('b2c_guide_recovery_tokens',function(Blueprint $t){$t->id();$t->unsignedBigInteger('cotizacion_id');$t->string('email_hash',64);$t->string('token_hash',64)->unique();$t->unsignedSmallInteger('attempts')->default(0);$t->unsignedSmallInteger('max_attempts')->default(10);$t->timestamp('expires_at');$t->timestamp('last_used_at')->nullable();$t->timestamp('revoked_at')->nullable();$t->timestamps();});
        config(['services.xperta'=>array_merge((array)config('services.xperta'),['base_url'=>'https://xperta.test','empresa'=>'empresa','ltd'=>'estafeta','corporativo'=>'corp','email'=>'api@test','password'=>'secret','api_key'=>'api-key','guide_pdf_path'=>'/api/v1/empresas/{empresa}/ltds/{ltd}/servicios/{service}/guia/pdf'])]);
        Cache::put('xperta:token:'.hash('sha256','https://xperta.test|corp|api@test'),'1|RAW',60);
    }

    public function test_required_uat_classifications(): void
    {
        $s=app(GuideRecoveryService::class);
        $this->assertSame('CREATION_FAILED',$s->classification($this->quote(['id'=>128,'guia_estatus'=>'ERROR_PROVEEDOR','guia_last_error_code'=>'XPERTA_PROVIDER_ERROR','guia_generation_attempts'=>2])));
        $this->assertSame('DOCUMENT_MISSING',$s->classification($this->quote(['id'=>107,'guia_id'=>'WB','tracking_number'=>'TR'])));
        $this->assertSame('QUOTE_EXPIRED',$s->classification($this->quote(['id'=>100,'quote_expires_at'=>now()->subMinute(),'guia_generation_attempts'=>99])));
    }

    public function test_fetch_pdf_uses_waybill_once_and_does_not_create_or_increment_attempts(): void
    {
        Storage::fake('local');Http::fake(['*'=>Http::response(['success'=>true,'data'=>['data'=>base64_encode("%PDF-1.4\n%%EOF")]],200)]);
        $q=$this->quote(['id'=>107,'guia_id'=>'8050000000112600113484','tracking_number'=>'0218429641','guia_generation_attempts'=>2]);$q->save();
        app(XpertaGuidePdfService::class)->fetch($q);
        $fresh=$q->fresh();$this->assertSame('8050000000112600113484',$fresh->guia_id);$this->assertSame('0218429641',$fresh->tracking_number);$this->assertSame(2,$fresh->guia_generation_attempts);Storage::disk('local')->assertExists($fresh->documento);
        Http::assertSentCount(1);Http::assertSent(fn($r)=>str_ends_with($r->url(),'/servicios/terrestre/guia/pdf')&&$r->data()===['token'=>base64_encode('1|RAW'),'wayBill'=>'8050000000112600113484']);
    }

    public function test_invalid_pdf_is_rejected_and_not_saved(): void
    {
        Storage::fake('local');Http::fake(['*'=>Http::response(['success'=>true,'data'=>['data'=>base64_encode('not-pdf')]],200)]);$q=$this->quote(['id'=>9,'guia_id'=>'WB']);$q->save();
        $this->expectExceptionMessage('XPERTA_PDF_INVALID');try{app(XpertaGuidePdfService::class)->fetch($q);}finally{$this->assertNull($q->fresh()->documento);}
    }

    public function test_case_107_crm_action_recovers_pdf_without_recreating_or_mutating_guide(): void
    {
        Storage::fake('local'); Mail::fake();
        Http::fake(['*'=>Http::response(['success'=>true,'data'=>['data'=>base64_encode("%PDF-1.4\n%%EOF")]],200)]);
        $q=$this->case107(); $before=$q->only(['guia_id','tracking_number','guia_generation_attempts','payment_status']);

        $response=$this->withoutMiddleware([\App\Http\Middleware\EnsureZigoPortalHost::class,\App\Http\Middleware\Authenticate::class,\App\Http\Middleware\RolesMiddleware::class,\App\Http\Middleware\VerifyCsrfToken::class])->from('/crm/guias')->post(route('crm.guias.recovery.pdf',$q));

        $response->assertRedirect('/crm/guias')->assertSessionHas('success');
        $fresh=$q->fresh();
        $this->assertSame($before,$fresh->only(array_keys($before)));
        Storage::disk('local')->assertExists($fresh->documento);
        $this->assertSame('RESOLVED',B2cGuideRecoveryCase::where('cotizacion_id',107)->value('status'));
        $this->assertDatabaseHas('b2c_guide_recovery_audits',['cotizacion_id'=>107,'action'=>'FETCH_PDF','result'=>'SUCCESS']);
        Mail::assertSent(GuideRecoveryMail::class,fn(GuideRecoveryMail $mail)=>$mail->kind==='generated');
        $this->assertDatabaseCount('b2c_guide_recovery_tokens',1);
        Http::assertSentCount(1);
    }

    public function test_case_107_provider_error_returns_functional_message_and_stays_document_missing(): void
    {
        Storage::fake('local'); Mail::fake();
        Http::fake(['*'=>Http::response(['success'=>false,'message'=>'token=secret rechazo funcional'],503)]);
        $q=$this->case107(); $before=$q->only(['guia_id','tracking_number','guia_generation_attempts','payment_status']);

        $response=$this->withoutMiddleware([\App\Http\Middleware\EnsureZigoPortalHost::class,\App\Http\Middleware\Authenticate::class,\App\Http\Middleware\RolesMiddleware::class,\App\Http\Middleware\VerifyCsrfToken::class])->from('/crm/guias')->post(route('crm.guias.recovery.pdf',$q));

        $response->assertRedirect('/crm/guias')->assertSessionHas('error');
        $fresh=$q->fresh();
        $this->assertNull($fresh->documento); $this->assertSame($before,$fresh->only(array_keys($before)));
        $this->assertSame('DOCUMENT_MISSING',B2cGuideRecoveryCase::where('cotizacion_id',107)->value('classification'));
        $this->assertSame('OPEN',B2cGuideRecoveryCase::where('cotizacion_id',107)->value('status'));
        $audit=B2cGuideRecoveryAudit::where('cotizacion_id',107)->where('action','FETCH_PDF')->firstOrFail();
        $this->assertSame('FAILURE',$audit->result); $this->assertSame(503,$audit->metadata['http_status']);
        $this->assertSame('XPERTA_HTTP_503',$audit->metadata['provider_code']);
        $this->assertStringNotContainsString('secret',$audit->metadata['provider_message']);
        Mail::assertNothingSent();
    }

    public function test_exact_stage_provider_exception_is_autoloadable_and_controlled(): void
    {
        $this->assertTrue(class_exists(XpertaProviderException::class));
        Http::fake(['*'=>Http::response(['success'=>false,'message'=>'rechazo funcional'],503,['X-Request-ID'=>'request-107'])]);
        $q=$this->case107();
        try {
            app(XpertaGuidePdfService::class)->fetch($q);
            $this->fail('Expected the typed provider exception.');
        } catch (XpertaProviderException $exception) {
            $this->assertSame('XPERTA_HTTP_503',$exception->errorCode);
            $this->assertSame(503,$exception->httpStatus);
            $this->assertSame('request-107',$exception->correlationId);
            $this->assertSame(2,$q->fresh()->guia_generation_attempts);
            $this->assertSame('8050000000112600113484',$q->fresh()->guia_id);
            $this->assertSame('0218429641',$q->fresh()->tracking_number);
        }
    }

    public function test_case_133_explicit_link_is_mailed_audited_and_safe_to_repeat(): void
    {
        Mail::fake();
        $q=$this->quote(['id'=>133,'remitente_email'=>'eg2bourne@gmail.com','quote_expires_at'=>now()->subMinute()]);
        $q->id=133;
        $q->save();
        app(GuideRecoveryService::class)->issue($q,'eg2bourne@gmail.com','pending');
        $response=$this->withoutMiddleware([\App\Http\Middleware\EnsureZigoPortalHost::class,\App\Http\Middleware\Authenticate::class,\App\Http\Middleware\RolesMiddleware::class,\App\Http\Middleware\VerifyCsrfToken::class])->post(route('crm.guias.recovery.link',$q));
        $response->assertRedirect(route('crm.guias.index'))->assertSessionHas('success','Enlace de recuperación enviado correctamente.');
        $this->assertSame('QUOTE_EXPIRED',app(GuideRecoveryService::class)->classification($q->fresh()));
        Mail::assertSent(GuideRecoveryMail::class,2);
        Mail::assertSent(GuideRecoveryMail::class,function(GuideRecoveryMail $mail){
            $this->assertTrue($mail->hasTo('eg2bourne@gmail.com'));
            $url=route('guide-recovery.show',$mail->recoveryToken);
            $this->assertStringContainsString('/envio/recuperar/',$url);
            $this->assertStringNotContainsString('/crm/',$url);
            return true;
        });
        $this->assertSame(1,\App\Models\B2cGuideRecoveryToken::whereNull('revoked_at')->count());
        $this->assertSame(2,B2cGuideRecoveryAudit::where('cotizacion_id',133)->where('action','TOKEN_ISSUED')->where('result','SUCCESS')->count());
        $this->assertSame(2,B2cGuideRecoveryAudit::where('cotizacion_id',133)->where('action','MAIL_SENT')->where('result','SUCCESS')->count());
    }

    public function test_explicit_link_does_not_report_success_when_mail_fails(): void
    {
        $q=$this->quote(['id'=>133,'remitente_email'=>'eg2bourne@gmail.com','quote_expires_at'=>now()->subMinute()]);
        $q->id=133;
        $q->save();
        Mail::shouldReceive('to')->once()->with('eg2bourne@gmail.com')->andThrow(new \RuntimeException('smtp unavailable'));
        $response=$this->withoutMiddleware([\App\Http\Middleware\EnsureZigoPortalHost::class,\App\Http\Middleware\Authenticate::class,\App\Http\Middleware\RolesMiddleware::class,\App\Http\Middleware\VerifyCsrfToken::class])->post(route('crm.guias.recovery.link',$q));
        $response->assertRedirect(route('crm.guias.index'))->assertSessionHas('error');
        $this->assertDatabaseHas('b2c_guide_recovery_audits',['cotizacion_id'=>133,'action'=>'MAIL_FAILED','result'=>'FAILURE']);
        $this->assertDatabaseMissing('b2c_guide_recovery_audits',['cotizacion_id'=>133,'action'=>'ISSUE_LINK','result'=>'SUCCESS']);
        $this->assertSame(0,\App\Models\B2cGuideRecoveryToken::whereNull('revoked_at')->count());
    }

    private function case107(): B2cCotizacion
    {
        $quote=new B2cCotizacion(['remitente_email'=>'buyer@example.test','payment_status'=>'approved','payment_verified_at'=>now(),'precio'=>100,'service_code'=>'terrestre','guia_id'=>'8050000000112600113484','tracking_number'=>'0218429641','guia_generation_attempts'=>2]);
        $quote->id=107; $quote->save(); return $quote;
    }

    private function quote(array $fields): B2cCotizacion { return new B2cCotizacion(array_merge(['payment_status'=>'approved','payment_verified_at'=>now(),'service_code'=>'terrestre','precio'=>100],$fields)); }
}
