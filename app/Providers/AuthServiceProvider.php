<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::define('isSysAdmin', function ($user) {
            return $user->roles->first()->slug == 'sysadmin';
        });

        Gate::define('isAdmin', function ($user) {
            return $user->roles->first()->slug == 'admin';
        });

        Gate::define('isContraloria', function ($user) {
            return $user->roles->first()->slug == 'contraloria';
        });

        Gate::define('isAuditoria', function ($user) {
            return $user->roles->first()->slug == 'auditoria';
        });

        Gate::define('isComercial', function ($user) {
            return $user->roles->first()->slug == 'comercial';
        });

        Gate::define('isAdminOps', function ($user) {
            return $user->roles->first()->slug == 'adminops';
        });

        Gate::define('isOperaciones', function ($user) {
            return $user->roles->first()->slug == 'operaciones';
        });

        Gate::define('isCliente', function ($user) {
            return $user->roles->first()->slug == 'cliente';
        });

        Gate::define('isUsuario', function ($user) {
            return $user->roles->first()->slug == 'usuario';
        });
    }
}
