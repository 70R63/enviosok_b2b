<?php
namespace Tests\Unit;
use App\Support\Presentation\AddressPresenter;
use PHPUnit\Framework\TestCase;
final class AddressPresenterTest extends TestCase{
 public function test_it_formats_legacy_and_structured_addresses_without_nested_noise():void{
  $this->assertSame('Calle Uno 10',AddressPresenter::full('Calle Uno 10'));
  $address=['street'=>'Calle Uno','number'=>'10','interior'=>'2','colony'=>'Centro','postal_code'=>'64000','municipality'=>'Monterrey','state'=>'Nuevo León','country'=>'México','ignored'=>['México','noise']];
  $this->assertSame('Calle Uno, 10, 2, Centro, 64000, Monterrey, Nuevo León, México',AddressPresenter::full($address));
  $this->assertSame('Monterrey, Nuevo León',AddressPresenter::compact($address));
  $this->assertSame('México',AddressPresenter::full(['country'=>'México','pais'=>'México']));
  $this->assertSame('—',AddressPresenter::full(null));
 }
}
