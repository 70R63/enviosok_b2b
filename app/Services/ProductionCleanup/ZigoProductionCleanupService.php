<?php

namespace App\Services\ProductionCleanup;

use App\Models\User;
use App\Services\CRM\CrmUserClassificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ZigoProductionCleanupService
{
    private const DELETE_ORDER = [
        'api_webhook_delivery_attempts',
        'api_webhook_deliveries',
        'api_webhook_endpoints',
        'b2c_identity_verification_events',
        'b2c_checkout_debt_allocations',
        'b2c_adeudos',
        'b2c_incidencias',
        'b2c_invoice_requests',
        'api_billing_request_items',
        'api_billing_requests',
        'b2c_identity_verifications',
        'b2c_fiscal_profiles',
        'b2c_direcciones',
        'b2c_saldo_reversals',
        'b2c_movimientos_saldo',
        'b2c_recargas',
        'b2c_saldos',
        'guias_paquetes',
        'guias_externas',
        'masivas',
        'guias',
        'pagos',
        'reporte_pagos',
        'ajustes',
        'saldos',
        'b2c_cotizaciones',
        'api_usage_logs',
        'api_client_products',
        'api_keys',
        'api_clients',
        'crm_clients',
        'personal_access_tokens',
        'users_permisos',
        'users_roles',
        'users',
        'empresa_ltds',
        'empresa_empresas',
        'clientes',
        'sucursals',
        'empresas',
    ];

    private const PRESERVED_TABLES = [
        'roles',
        'permisos',
        'roles_permisos',
        'zigo_postal_codes',
        'cps',
        'sepomex',
        'catalogos',
        'catalogo_elementos',
        'cfg_ltds',
        'ltds',
        'servicios',
        'api_products',
        'zigo_provider_sources',
        'zigo_shipping_agreements',
        'zigo_agreement_services',
        'zigo_contract_rates',
        'zigo_provider_quote_observations',
        'zigo_provider_rate_cards',
        'zigo_provider_rate_lines',
        'zigo_provider_rate_references',
        'zigo_pricing_rules',
        'zigo_pricing_adjustments',
        'zigo_client_pricing_rules',
        'migrations',
    ];

    private array $ids = [];
    private array $files = [];

    public function __construct(
        private CrmUserClassificationService $classification
    ) {
    }

    public function inventory(
        array $keepEmails,
        bool $purgeCommercialClients = false,
        bool $purgeApiClients = false
    ): array
    {
        $keepEmails = collect($keepEmails)
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();
        $warnings = [];
        $blocks = [];

        if ($keepEmails->isEmpty()) {
            $blocks[] = 'La allowlist --keep-admin está vacía.';
        }

        $protectedUsers = Schema::hasTable('users')
            ? DB::table('users')
                ->whereIn(DB::raw('LOWER(email)'), $keepEmails->all())
                ->select('id', 'email', 'empresa_id')
                ->get()
            : collect();

        foreach ($keepEmails as $email) {
            if (!$protectedUsers->contains(
                fn ($user) => mb_strtolower($user->email) === $email
            )) {
                $blocks[] = 'No existe el administrador protegido: ' . $email;
            }
        }

        $protectedSysadmins = $this->protectedSysadmins(
            $protectedUsers->pluck('id')->all()
        );
        if ($protectedSysadmins->isEmpty()) {
            $blocks[] = 'La allowlist no contiene un usuario con rol sysadmin.';
        }

        $this->ids['protected_users'] = $protectedUsers->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->ids['candidate_users'] = Schema::hasTable('users')
            ? DB::table('users')->whereNotIn('id', $this->ids['protected_users'])->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];
        $userCategories = $this->categorizeCandidateUsers();
        $this->ids['candidate_internal_users'] = $userCategories['internal'];
        $this->ids['candidate_b2b_users'] = $userCategories['b2b'];
        $this->ids['candidate_b2c_users'] = $userCategories['b2c'];
        $this->ids['blocked_users'] = $userCategories['blocked'];
        $this->ids['candidate_users'] = collect([
            ...$userCategories['internal'],
            ...$userCategories['b2b'],
            ...$userCategories['b2c'],
        ])->unique()->values()->all();

        if ($this->ids['blocked_users'] !== []) {
            $blocks[] = 'Existen usuarios sin clasificación determinista.';
        }

        $b2cCompanyId = $this->positiveInt(config('services.b2c.empresa_id'));
        if ($b2cCompanyId === null) {
            $blocks[] = 'services.b2c.empresa_id no es numérico y positivo.';
        }

        $this->ids['protected_companies'] = collect([$b2cCompanyId])
            ->merge($protectedUsers->pluck('empresa_id'))
            ->filter(fn ($id) => $this->positiveInt($id) !== null)
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
        $this->ids['candidate_companies'] = Schema::hasTable('empresas')
            ? DB::table('empresas')->whereNotIn('id', $this->ids['protected_companies'])->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];

        $this->ids['protected_api_clients'] = $this->protectedApiClients();
        $this->ids['protected_crm_clients'] = $this->protectedCrmClients();
        $reviewCommercialIds = Schema::hasTable('crm_clients')
            ? DB::table('crm_clients')
                ->whereNotIn('id', $this->ids['protected_crm_clients'])
                ->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];
        $reviewApiIds = $this->reviewApiClientIds();
        $this->ids['candidate_crm_clients'] = $purgeCommercialClients
            ? $reviewCommercialIds
            : [];
        $this->ids['candidate_api_clients'] = $purgeApiClients
            ? $reviewApiIds
            : [];
        $this->ids['protected_api_clients'] = Schema::hasTable('api_clients')
            ? DB::table('api_clients')->whereNotIn('id', $this->ids['candidate_api_clients'])->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];
        $this->ids['candidate_quotes'] = $this->idsBy(
            'b2c_cotizaciones',
            'user_id',
            $this->ids['candidate_users']
        );
        $this->ids['candidate_identities'] = $this->idsBy(
            'b2c_identity_verifications',
            'user_id',
            $this->ids['candidate_users']
        );
        $this->ids['candidate_invoices'] = $this->idsBy(
            'b2c_invoice_requests',
            'user_id',
            $this->ids['candidate_users']
        );
        $this->ids['candidate_api_billings'] = $this->idsBy(
            'api_billing_requests',
            'api_client_id',
            $this->ids['candidate_api_clients']
        );
        $this->ids['candidate_webhook_deliveries'] = $this->idsBy(
            'api_webhook_deliveries',
            'api_client_id',
            $this->ids['candidate_api_clients']
        );
        $this->ids['candidate_guides'] = $this->candidateGuideIds();

        $this->detectPreservedReferences($blocks);
        $this->detectUnknownForeignKeys($blocks);
        if (Schema::hasTable('b2c_cotizacions') && DB::table('b2c_cotizacions')->exists()) {
            $blocks[] = 'La tabla heredada b2c_cotizacions contiene datos sin relación determinista.';
        }
        $this->collectFiles();

        $reviewCounts = [];
        if (!$purgeCommercialClients && $reviewCommercialIds !== []) {
            $reviewCounts['commercial_clients'] = count($reviewCommercialIds);
            $blocks[] = 'Existen clientes comerciales en REVISAR; falta --purge-commercial-clients.';
        }
        if (!$purgeApiClients && $reviewApiIds !== []) {
            $reviewCounts['api_clients'] = count($reviewApiIds);
            $blocks[] = 'Existen clientes API en REVISAR; falta --purge-api-clients.';
        }

        $userPartition = $this->userPartition($protectedUsers);
        $counts = $this->candidateCounts($userPartition);
        $actualUsersTotal = Schema::hasTable('users')
            ? DB::table('users')->count()
            : 0;
        $partitionTotal = array_sum($userPartition);

        if ($partitionTotal !== $actualUsersTotal) {
            $blocks[] = 'CRÍTICO: la ecuación de usuarios no coincide.';
            $warnings[] = 'CRÍTICO: protected + candidates + blocked != users_total.';
        }
        if (array_intersect(
            $this->ids['protected_users'],
            $this->ids['candidate_users']
        ) !== []) {
            $blocks[] = 'CRÍTICO: un usuario protegido aparece como candidato.';
        }
        $usersNextId = Schema::hasTable('users')
            ? ((int) DB::table('users')->max('id')) + 1
            : 1;
        $commit = $this->gitCommit();

        if ($this->ids['candidate_users'] === []) {
            $warnings[] = 'No hay usuarios candidatos fuera de la allowlist.';
        }
        if ($this->ids['candidate_crm_clients'] !== []) {
            $warnings[] = 'Todos los clientes comerciales se consideran de prueba; revisar el inventario antes de producción.';
        }
        if ($this->ids['candidate_api_clients'] !== []) {
            $warnings[] = 'Los clientes API no vinculados a administradores protegidos se consideran candidatos.';
        }

        return [
            'date' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'mode' => 'dry-run',
            'commit' => $commit,
            'protected_administrators' => $protectedSysadmins
                ->pluck('email')->values()->all(),
            'entities_detected' => array_keys($counts),
            'protected_counts' => [
                'users' => count($this->ids['protected_users']),
                'sysadmins' => $protectedSysadmins->count(),
                'companies' => count($this->ids['protected_companies']),
                'api_clients' => count($this->ids['protected_api_clients']),
                'api_products' => Schema::hasTable('api_products')
                    ? DB::table('api_products')->count()
                    : 0,
            ],
            'candidate_counts' => $counts,
            'user_equation' => array_merge($userPartition, [
                'users_total' => $actualUsersTotal,
                'partition_total' => $partitionTotal,
                'matches' => $partitionTotal === $actualUsersTotal,
            ]),
            'review_counts' => $reviewCounts,
            'blocks' => array_values(array_unique($blocks)),
            'warnings' => $warnings,
            'candidate_files' => array_map(
                fn ($file) => [
                    'disk' => $file['disk'],
                    'fingerprint' => hash('sha256', $file['path']),
                ],
                $this->files
            ),
            'deletion_order' => array_values(array_filter(
                self::DELETE_ORDER,
                fn ($table) => Schema::hasTable($table)
            )),
            'users_next_id' => $usersNextId,
            'READY_TO_EXECUTE' => $blocks === []
                && $reviewCounts === []
                && $partitionTotal === $actualUsersTotal,
        ];
    }

    public function execute(array $report): array
    {
        if (!($report['READY_TO_EXECUTE'] ?? false)) {
            throw new RuntimeException('El inventario contiene bloqueos.');
        }

        DB::transaction(function () {
            foreach (self::DELETE_ORDER as $table) {
                $this->deleteCandidates($table);
            }
        });

        $fileWarnings = [];
        foreach ($this->files as $file) {
            try {
                if (Storage::disk($file['disk'])->exists($file['path'])) {
                    Storage::disk($file['disk'])->delete($file['path']);
                }
            } catch (Throwable $exception) {
                $fileWarnings[] = 'No se pudo eliminar archivo '
                    . hash('sha256', $file['path']) . '.';
            }
        }

        $report['mode'] = 'execute';
        $report['executed_at'] = now()->toIso8601String();
        $report['warnings'] = array_merge(
            $report['warnings'],
            $fileWarnings
        );

        return $report;
    }

    private function candidateCounts(array $userPartition): array
    {
        $counts = [
            'personal_zigo' => $userPartition['candidate_internal'],
            'users_b2c' => $userPartition['candidate_b2c'],
            'users_b2b' => $userPartition['candidate_b2b'],
            'prospects' => $this->countWhere('crm_clients', fn ($query) => $query->where('commercial_status', 'prospecto')),
            'commercial_clients' => count($this->ids['candidate_crm_clients']),
            'companies' => count($this->ids['candidate_companies']),
            'b2c_quotes' => count($this->ids['candidate_quotes']),
            'guides' => count($this->ids['candidate_guides']),
            'payments' => $this->countBy('pagos', 'usuario_id', $this->ids['candidate_users']),
            'recharges' => $this->countBy('b2c_recargas', 'user_id', $this->ids['candidate_users']),
            'balances' => $this->countBy('b2c_saldos', 'user_id', $this->ids['candidate_users']),
            'balance_movements' => $this->countBy('b2c_movimientos_saldo', 'user_id', $this->ids['candidate_users']),
            'debts' => $this->countBy('b2c_adeudos', 'user_id', $this->ids['candidate_users']),
            'incidents' => $this->countBy('b2c_incidencias', 'user_id', $this->ids['candidate_users']),
            'identity_verifications' => $this->countBy('b2c_identity_verifications', 'user_id', $this->ids['candidate_users']),
            'fiscal_profiles' => $this->countBy('b2c_fiscal_profiles', 'user_id', $this->ids['candidate_users']),
            'invoice_requests' => $this->countBy('b2c_invoice_requests', 'user_id', $this->ids['candidate_users']),
            'api_clients' => count($this->ids['candidate_api_clients']),
            'files' => count($this->files),
        ];
        $counts['candidate_users_total'] = count(
            $this->ids['candidate_users']
        );

        return $counts;
    }

    private function deleteCandidates(string $table): void
    {
        if (!Schema::hasTable($table)) return;
        $userIds = $this->ids['candidate_users'];
        $companyIds = $this->ids['candidate_companies'];
        $apiClientIds = $this->ids['candidate_api_clients'];
        $crmClientIds = $this->ids['candidate_crm_clients'];

        $scopes = [
            'api_webhook_delivery_attempts' => ['api_webhook_delivery_id', $this->ids['candidate_webhook_deliveries']],
            'api_webhook_deliveries' => ['id', $this->ids['candidate_webhook_deliveries']],
            'api_webhook_endpoints' => ['api_client_id', $apiClientIds],
            'b2c_identity_verification_events' => ['identity_verification_id', $this->ids['candidate_identities']],
            'b2c_checkout_debt_allocations' => ['user_id', $userIds],
            'b2c_adeudos' => ['user_id', $userIds],
            'b2c_incidencias' => ['user_id', $userIds],
            'b2c_invoice_requests' => ['id', $this->ids['candidate_invoices']],
            'api_billing_request_items' => ['api_billing_request_id', $this->ids['candidate_api_billings']],
            'api_billing_requests' => ['id', $this->ids['candidate_api_billings']],
            'b2c_identity_verifications' => ['id', $this->ids['candidate_identities']],
            'b2c_fiscal_profiles' => ['user_id', $userIds],
            'b2c_direcciones' => ['user_id', $userIds],
            'b2c_saldo_reversals' => ['user_id', $userIds],
            'b2c_movimientos_saldo' => ['user_id', $userIds],
            'b2c_recargas' => ['user_id', $userIds],
            'b2c_saldos' => ['user_id', $userIds],
            'guias_paquetes' => ['guia_id', $this->ids['candidate_guides']],
            'guias_externas' => ['user_id', $userIds],
            'masivas' => ['user_id', $userIds],
            'guias' => ['id', $this->ids['candidate_guides']],
            'pagos' => ['usuario_id', $userIds],
            'reporte_pagos' => ['user_id', $userIds],
            'ajustes' => ['user_id', $userIds],
            'saldos' => ['empresa_id', $companyIds],
            'b2c_cotizaciones' => ['id', $this->ids['candidate_quotes']],
            'api_usage_logs' => ['api_client_id', $apiClientIds],
            'api_client_products' => ['api_client_id', $apiClientIds],
            'api_keys' => ['api_client_id', $apiClientIds],
            'api_clients' => ['id', $apiClientIds],
            'crm_clients' => ['id', $crmClientIds],
            'personal_access_tokens' => ['tokenable_id', $userIds],
            'users_permisos' => ['user_id', $userIds],
            'users_roles' => ['user_id', $userIds],
            'users' => ['id', $userIds],
            'empresa_ltds' => ['empresa_id', $companyIds],
            'empresa_empresas' => ['empresa_id', $companyIds],
            'clientes' => ['empresa_id', $companyIds],
            'sucursals' => ['empresa_id', $companyIds],
            'empresas' => ['id', $companyIds],
        ];

        if (!isset($scopes[$table])) return;
        [$column, $ids] = $scopes[$table];
        if ($ids === [] || !Schema::hasColumn($table, $column)) return;
        $query = DB::table($table)->whereIn($column, $ids);
        if ($table === 'personal_access_tokens' && Schema::hasColumn($table, 'tokenable_type')) {
            $query->where('tokenable_type', 'App\\Models\\User');
        }
        $query->delete();
    }

    private function detectPreservedReferences(array &$blocks): void
    {
        foreach (self::PRESERVED_TABLES as $table) {
            if (!Schema::hasTable($table)) continue;
            foreach (['user_id', 'created_by', 'updated_by', 'managed_by_user_id'] as $column) {
                if (!Schema::hasColumn($table, $column)) continue;
                $count = DB::table($table)->whereIn($column, $this->ids['candidate_users'])->count();
                if ($count > 0) {
                    $blocks[] = "{$table}.{$column} referencia {$count} usuario(s) candidato(s) y la tabla está protegida.";
                }
            }
        }
    }

    private function detectUnknownForeignKeys(array &$blocks): void
    {
        $database = DB::getDatabaseName();
        $known = array_merge(self::DELETE_ORDER, self::PRESERVED_TABLES);
        $rows = DB::select(
            'SELECT table_name AS child_table, referenced_table_name AS parent_table '
            . 'FROM information_schema.key_column_usage '
            . 'WHERE table_schema = ? AND referenced_table_name IS NOT NULL',
            [$database]
        );
        foreach ($rows as $row) {
            if (!in_array($row->child_table, $known, true)) {
                $blocks[] = 'Relación desconocida desde ' . $row->child_table
                    . ' hacia ' . $row->parent_table . '.';
            }
        }
    }

    private function collectFiles(): void
    {
        $this->files = [];
        $this->collectPaths('b2c_identity_verifications', ['ine_front', 'ine_back', 'selfie_with_ine'], 'user_id', $this->ids['candidate_users'], 'local');
        $this->collectPaths('b2c_invoice_requests', ['pdf_path', 'xml_path'], 'user_id', $this->ids['candidate_users'], 'local');
        $this->collectPaths('b2c_cotizaciones', ['documento'], 'user_id', $this->ids['candidate_users'], 'public');
        $this->collectPaths('api_billing_requests', ['pdf_path', 'xml_path'], 'api_client_id', $this->ids['candidate_api_clients'], 'local');
        $this->collectPaths('masivas', ['archivo_nombre', 'archivo_fallo', 'ruta_zip'], 'user_id', $this->ids['candidate_users'], 'local');
        $this->files = collect($this->files)->unique(fn ($file) => $file['disk'] . '|' . $file['path'])->values()->all();
    }

    private function collectPaths(string $table, array $columns, string $scopeColumn, array $ids, string $disk): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $scopeColumn) || $ids === []) return;
        $columns = array_values(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column)));
        if ($columns === []) return;
        foreach (DB::table($table)->whereIn($scopeColumn, $ids)->select($columns)->get() as $row) {
            foreach ($columns as $column) {
                $path = trim((string) ($row->{$column} ?? ''));
                if ($path !== '' && !filter_var($path, FILTER_VALIDATE_URL)) {
                    $this->files[] = ['disk' => $disk, 'path' => $path];
                }
            }
        }
    }

    private function protectedSysadmins(array $ids)
    {
        if ($ids === [] || !Schema::hasTable('users_roles')) return collect();
        return DB::table('users')->join('users_roles', 'users.id', '=', 'users_roles.user_id')->join('roles', 'roles.id', '=', 'users_roles.roles_id')->whereIn('users.id', $ids)->where('roles.slug', 'sysadmin')->select('users.id', 'users.email')->distinct()->get();
    }

    private function protectedApiClients(): array
    {
        if (!Schema::hasTable('api_clients')) return [];
        return DB::table('api_clients')
            ->whereIn('user_id', $this->ids['protected_users'])
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function protectedCrmClients(): array
    {
        if (
            !Schema::hasTable('api_clients')
            || !Schema::hasColumn('api_clients', 'crm_client_id')
        ) {
            return [];
        }

        return DB::table('api_clients')
            ->whereIn('id', $this->ids['protected_api_clients'])
            ->whereNotNull('crm_client_id')
            ->pluck('crm_client_id')->map(fn ($id) => (int) $id)
            ->unique()->values()->all();
    }

    private function reviewApiClientIds(): array
    {
        if (!Schema::hasTable('api_clients')) return [];
        return DB::table('api_clients')
            ->whereNotIn('id', $this->ids['protected_api_clients'])
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function userPartition($protectedUsers): array
    {
        return [
            'protected' => $protectedUsers->count(),
            'candidate_internal' => count(
                $this->ids['candidate_internal_users']
            ),
            'candidate_b2b' => count(
                $this->ids['candidate_b2b_users']
            ),
            'candidate_b2c' => count(
                $this->ids['candidate_b2c_users']
            ),
            'blocked_unclassified' => count(
                $this->ids['blocked_users']
            ),
        ];
    }

    private function categorizeCandidateUsers(): array
    {
        $categories = [
            'internal' => [],
            'b2b' => [],
            'b2c' => [],
            'blocked' => [],
        ];
        $users = User::with('roles')
            ->whereIn('id', $this->ids['candidate_users'])
            ->get();

        foreach ($users as $user) {
            $category = $this->classification->classify($user);
            if ($category === CrmUserClassificationService::INTERNAL) {
                $categories['internal'][] = (int) $user->id;
            } elseif ($category === CrmUserClassificationService::B2B) {
                $categories['b2b'][] = (int) $user->id;
            } elseif ($category === CrmUserClassificationService::B2C) {
                $categories['b2c'][] = (int) $user->id;
            } else {
                $categories['blocked'][] = (int) $user->id;
            }
        }

        return $categories;
    }

    private function candidateGuideIds(): array
    {
        if (!Schema::hasTable('guias')) return [];
        $ids = collect();
        if (Schema::hasColumn('guias', 'user_id')) {
            $ids = $ids->merge($this->idsBy('guias', 'user_id', $this->ids['candidate_users']));
        }
        if (Schema::hasColumn('guias', 'empresa_id')) {
            $ids = $ids->merge($this->idsBy('guias', 'empresa_id', $this->ids['candidate_companies']));
        }
        if (Schema::hasColumn('b2c_cotizaciones', 'guia_id')) {
            $ids = $ids->merge(
                DB::table('b2c_cotizaciones')
                    ->whereIn('id', $this->ids['candidate_quotes'])
                    ->whereNotNull('guia_id')->pluck('guia_id')
            );
        }
        return $ids->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function idsBy(string $table, string $column, array $ids): array
    {
        if ($ids === [] || !Schema::hasTable($table) || !Schema::hasColumn($table, $column)) return [];
        return DB::table($table)->whereIn($column, $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function countBy(string $table, string $column, array $ids): int
    {
        if ($ids === [] || !Schema::hasTable($table) || !Schema::hasColumn($table, $column)) return 0;
        return DB::table($table)->whereIn($column, $ids)->count();
    }

    private function countWhere(string $table, callable $callback): int
    {
        if (!Schema::hasTable($table)) return 0;
        return $callback(DB::table($table))->count();
    }

    private function allIds(string $table): array
    {
        return Schema::hasTable($table) ? DB::table($table)->pluck('id')->map(fn ($id) => (int) $id)->all() : [];
    }

    private function positiveInt($value): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : $id;
    }

    private function gitCommit(): ?string
    {
        $output = [];
        $code = 1;
        exec('git rev-parse HEAD 2> NUL', $output, $code);
        return $code === 0 ? trim((string) ($output[0] ?? '')) : null;
    }
}
