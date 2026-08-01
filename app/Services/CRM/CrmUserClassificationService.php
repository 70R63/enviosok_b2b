<?php

namespace App\Services\CRM;

use App\Models\User;

class CrmUserClassificationService
{
    public const INTERNAL = 'internos';
    public const B2B = 'b2b';
    public const B2C = 'b2c';
    public const UNCLASSIFIED = 'sin_clasificar';

    private const INTERNAL_ROLES = [
        'sysadmin',
        'admin',
        'adminops',
        'operaciones',
        'auditoria',
        'contraloria',
        'comercial',
    ];

    private const BUSINESS_ROLES = [
        'cliente',
        'usuario',
    ];

    public function classify(User $user): string
    {
        $roles = $user->roles
            ->pluck('slug')
            ->map(fn ($slug) => mb_strtolower(trim((string) $slug)))
            ->filter()
            ->unique()
            ->values();

        if ($roles->intersect(self::INTERNAL_ROLES)->isNotEmpty()) {
            return $roles->diff(self::INTERNAL_ROLES)->isEmpty()
                ? self::INTERNAL
                : self::UNCLASSIFIED;
        }

        if ($roles->count() !== 1 || !$roles->contains('cliente') && !$roles->contains('usuario')) {
            return self::UNCLASSIFIED;
        }

        $empresaId = $this->validB2cCompanyId();
        $userCompanyId = filter_var(
            $user->empresa_id,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($userCompanyId === false) {
            return self::UNCLASSIFIED;
        }

        if ($roles->contains('cliente')) {
            if ($empresaId === null) {
                return self::UNCLASSIFIED;
            }

            return $userCompanyId === $empresaId
                ? self::B2C
                : self::B2B;
        }

        return self::B2B;
    }

    public function formType(User $user): ?string
    {
        return match ($this->classify($user)) {
            self::INTERNAL => 'interno',
            self::B2B => 'b2b',
            self::B2C => 'b2c_asistido',
            default => null,
        };
    }

    public function internalRoles(): array
    {
        return self::INTERNAL_ROLES;
    }

    public function businessRoles(): array
    {
        return self::BUSINESS_ROLES;
    }

    public function validB2cCompanyId(): ?int
    {
        $value = config('services.b2c.empresa_id');
        $empresaId = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        return $empresaId === false ? null : $empresaId;
    }
}
