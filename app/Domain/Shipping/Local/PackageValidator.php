<?php

namespace App\Domain\Shipping\Local;

use App\Domain\Shipping\Local\Models\LocalShippingPackageRule;
use Illuminate\Validation\ValidationException;

final class PackageValidator
{
    public function validate(LocalShippingPackageRule $rule, array $package): void
    {
        if ((string) $package['weight'] > (string) $rule->max_weight_kg) throw ValidationException::withMessages(['peso'=>'El peso excede el límite del paquete.']);
        if ($rule->package_type === 'sobre') return;
        $actual = [(string)$package['length'],(string)$package['width'],(string)$package['height']];
        $limits = [(string)$rule->max_dimension_1_cm,(string)$rule->max_dimension_2_cm,(string)$rule->max_dimension_3_cm];
        usort($actual, fn($a,$b)=>(float)$b<=>(float)$a); usort($limits, fn($a,$b)=>(float)$b<=>(float)$a);
        foreach ($actual as $i=>$dimension) if ((float)$dimension > (float)$limits[$i]) throw ValidationException::withMessages(['dimensions'=>'Las dimensiones exceden el límite permitido.']);
    }
}
