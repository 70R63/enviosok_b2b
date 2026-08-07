<?php
namespace Tests\Feature;

use App\Models\B2cCotizacion;
use App\Services\Shipping\GuideRecoveryService;
use App\Services\Shipping\Xperta\XpertaGuidePdfService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class GuestGuideRecoveryClosureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp(); config(['database.default'=>'sqlite','database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>false]]);DB::purge('sqlite');DB::reconnect('sqlite');
        Schema::create('b2c_cotizaciones',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id')->nullable();$t->string('referencia')->nullable();$t->string('remitente_email')->nullable();$t->string('destinatario_email')->nullable();$t->string('payment_status')->nullable();$t->timestamp('payment_verified_at')->nullable();$t->decimal('payment_verified_amount',10,2)->nullable();$t->decimal('precio',10,2)->nullable();$t->timestamp('quote_expires_at')->nullable();$t->string('guia_id')->nullable();$t->string('tracking_number')->nullable();$t->string('documento')->nullable();$t->string('guia_estatus')->nullable();$t->string('estatus')->nullable();$t->string('guia_last_error_code')->nullable();$t->text('guia_last_error_message')->nullable();$t->unsignedInteger('guia_generation_attempts')->default(0);$t->string('service_code')->nullable();$t->string('servicio')->nullable();$t->string('guia_label_format')->nullable();$t->timestamp('guia_recovered_at')->nullable();$t->unsignedBigInteger('guia_provider_request_number')->nullable();$t->timestamps();});
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

    private function quote(array $fields): B2cCotizacion { return new B2cCotizacion(array_merge(['payment_status'=>'approved','payment_verified_at'=>now(),'service_code'=>'terrestre','precio'=>100],$fields)); }
}
