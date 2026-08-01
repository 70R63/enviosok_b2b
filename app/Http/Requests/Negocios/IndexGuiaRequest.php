<?php

namespace App\Http\Requests\Negocios;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class IndexGuiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'numero_solicitud' => ['nullable', 'integer', 'min:1'],
            'rastreo_estatus' => ['nullable', 'integer', 'min:1'],
            'fecha_inicio' => ['nullable', 'date_format:Y-m-d'],
            'fecha_fin' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_inicio.date_format' => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            'fecha_fin.date_format' => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'fecha_fin.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (!$this->filled('fecha_inicio') || !$this->filled('fecha_fin')) {
                return;
            }

            try {
                $inicio = CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->input('fecha_inicio'));
                $fin = CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->input('fecha_fin'));
            } catch (\Throwable $exception) {
                return;
            }

            if ($inicio && $fin && $inicio->diffInDays($fin, false) > 366) {
                $validator->errors()->add(
                    'fecha_fin',
                    'El rango de fechas no puede exceder 366 días.'
                );
            }
        });
    }
}
