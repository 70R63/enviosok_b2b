<?php

namespace App\Domain\Network\Channels\B2C;

use App\Domain\Network\Channels\B2C\Models\{TenantCustomerAddress, TenantCustomerProfile};
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Services\ZigoPostalCodeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TenantCustomerAddressService
{
    public function __construct(private ZigoPostalCodeService $postal) {}

    public function canonical(array $data): array
    {
        $lookup = $this->postal->lookup($data['postal_code']);
        if (!($lookup['success'] ?? false)) throw ValidationException::withMessages(['postal_code'=>'No encontramos ese código postal en SEPOMEX.']);
        $valid = collect($lookup['colonias'])->contains(fn ($item) => hash_equals((string)$item['nombre'], (string)$data['settlement']));
        if (!$valid) throw ValidationException::withMessages(['settlement'=>'Selecciona una colonia válida para el código postal.']);
        $data['municipality'] = $lookup['municipio'];
        $data['state'] = $lookup['estado'];
        $data['is_default_origin'] = (bool)($data['is_default_origin'] ?? false);
        $data['is_default_destination'] = (bool)($data['is_default_destination'] ?? false);
        if (!in_array($data['address_type'], ['origin','both'], true)) $data['is_default_origin'] = false;
        if (!in_array($data['address_type'], ['destination','both'], true)) $data['is_default_destination'] = false;
        return $data;
    }

    public function save(Tenant $tenant, TenantCustomerProfile $profile, array $data, ?TenantCustomerAddress $address = null): TenantCustomerAddress
    {
        abort_unless((int)$profile->tenant_id === (int)$tenant->id, 404);
        if ($address) $this->assertOwned($address, $tenant, $profile);
        $data = $this->canonical($data);
        return DB::transaction(function () use ($tenant, $profile, $data, $address): TenantCustomerAddress {
            $duplicate = TenantCustomerAddress::where('tenant_id',$tenant->id)->where('customer_profile_id',$profile->id)
                ->where('postal_code',$data['postal_code'])->where('settlement',$data['settlement'])->where('street',$data['street'])
                ->where('exterior',$data['exterior'])->where(fn($query)=>filled($data['interior']??null)?$query->where('interior',$data['interior']):$query->whereNull('interior'))
                ->when($address,fn($query)=>$query->whereKeyNot($address->id))->first();
            $item = $address ?? $duplicate ?? new TenantCustomerAddress(['tenant_id'=>$tenant->id,'customer_profile_id'=>$profile->id]);
            if ($data['is_default_origin']) TenantCustomerAddress::where('tenant_id',$tenant->id)->where('customer_profile_id',$profile->id)->whereKeyNot($item->getKey() ?? 0)->update(['is_default_origin'=>false]);
            if ($data['is_default_destination']) TenantCustomerAddress::where('tenant_id',$tenant->id)->where('customer_profile_id',$profile->id)->whereKeyNot($item->getKey() ?? 0)->update(['is_default_destination'=>false]);
            $item->fill($data + ['is_active'=>true]);
            $item->is_active = true;
            $item->save();
            return $item->refresh();
        });
    }

    public function assertOwned(TenantCustomerAddress $address, Tenant $tenant, TenantCustomerProfile $profile): void
    {
        abort_unless((int)$address->tenant_id === (int)$tenant->id && (int)$address->customer_profile_id === (int)$profile->id, 404);
    }

    public function compatible(Tenant $tenant, TenantCustomerProfile $profile, string $uuid, string $side, array $snapshotAddress, ?string $errorKey = null): TenantCustomerAddress
    {
        $types = $side === 'origin' ? ['origin','both'] : ['destination','both'];
        $address = TenantCustomerAddress::where('tenant_id',$tenant->id)->where('customer_profile_id',$profile->id)->where('uuid',$uuid)->where('is_active',true)->whereIn('address_type',$types)->firstOrFail();
        if (!hash_equals((string)($snapshotAddress['postal_code']??''),$address->postal_code) || !hash_equals((string)($snapshotAddress['settlement']??''),$address->settlement)) {
            throw ValidationException::withMessages([$errorKey ?? $side.'_address_uuid'=>'La dirección guardada pertenece a otra ruta. Vuelve a cotizar para utilizarla.']);
        }
        return $address;
    }

    public function deactivate(TenantCustomerAddress $address, Tenant $tenant, TenantCustomerProfile $profile): void
    {
        $this->assertOwned($address,$tenant,$profile);
        $address->update(['is_active'=>false,'is_default_origin'=>false,'is_default_destination'=>false]);
    }
}
