<?php

namespace Tests\Unit;

use App\Domain\Shipping\Local\Models\{LocalShippingPackageRule,LocalShippingPricingRule,LocalShippingService};
use App\Domain\Shipping\Local\PackageValidator;
use App\Domain\Shipping\Local\Pricing\LocalPricingEngine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class TenantLogisticsEngineTest extends TestCase
{
    public function test_package_limits_and_orientation():void{$v=new PackageValidator();$envelope=new LocalShippingPackageRule(['package_type'=>'sobre','max_weight_kg'=>'1']);$v->validate($envelope,['weight'=>'1']);$this->expectException(ValidationException::class);$v->validate($envelope,['weight'=>'1.01']);}
    public function test_box_dimensions_accept_any_orientation():void{$rule=new LocalShippingPackageRule(['package_type'=>'caja','max_weight_kg'=>'40','max_dimension_1_cm'=>'60','max_dimension_2_cm'=>'50','max_dimension_3_cm'=>'40']);foreach([[60,50,40],[50,40,60],[40,60,50]]as$d)(new PackageValidator())->validate($rule,['weight'=>'40','length'=>$d[0],'width'=>$d[1],'height'=>$d[2]]);$this->addToAssertionCount(3);}
    /** @dataProvider distanceCases */
    public function test_distance_tiers_and_overage(int $meters,string $expected):void{$service=$this->service('DISTANCE_TIERS_OVERAGE',[$this->rule(1,'0','7','89'),$this->rule(2,'7.001','15','109'),$this->rule(3,'15.001','25','139'),$this->rule(4,'25.001',null,'139','25','20','PROPORTIONAL')]);self::assertSame($expected,(new LocalPricingEngine())->price($service,'caja',$meters)->totalAmount);}
    public static function distanceCases():array{return[[7000,'89.00'],[15000,'109.00'],[25000,'139.00'],[26500,'169.00']];}
    public function test_flat_package_flat_base_overage_and_ceil():void{$flat=$this->service('FLAT',[$this->rule(1,null,null,'179')]);self::assertSame('179.00',(new LocalPricingEngine())->price($flat,'sobre',null)->totalAmount);$package=$this->rule(2,null,null,'99');$package->package_type='caja';self::assertSame('99.00',(new LocalPricingEngine())->price($this->service('PACKAGE_FLAT',[$package]),'caja',null)->totalAmount);$base=$this->rule(3,null,null,'120','10','15','CEIL');self::assertSame('150.00',(new LocalPricingEngine())->price($this->service('BASE_PLUS_OVERAGE',[$base]),'caja',11100)->totalAmount);}
    private function service(string $strategy,array $rules):LocalShippingService{$s=new LocalShippingService(['name'=>'Demo','pricing_strategy'=>$strategy,'currency'=>'MXN']);$s->id=10;$s->setRelation('pricingRules',new Collection($rules));return$s;}
    private function rule(int$id,?string$from,?string$to,string$amount,?string$included=null,?string$overage=null,?string$rounding=null):LocalShippingPricingRule{$r=new LocalShippingPricingRule(['from_km'=>$from,'to_km'=>$to,'amount'=>$amount,'included_distance_km'=>$included,'overage_price_per_km'=>$overage,'overage_rounding'=>$rounding,'active'=>true]);$r->id=$id;return$r;}
}
