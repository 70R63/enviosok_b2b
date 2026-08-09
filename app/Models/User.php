<?php

namespace App\Models;

use App\Notifications\ZigoResetPasswordNotification;
use App\Traits\HasRolesAndPermisos;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    public function empresa(){ return $this->belongsTo(Empresa::class,'empresa_id'); }
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use HasRolesAndPermisos;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'apellido_paterno',
        'apellido_materno',
        'rfc',
        'email',
        'password',
        'empresa_id',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function tenantCustomerProfiles()
    {
        return $this->hasMany(\App\Domain\Network\Channels\B2C\Models\TenantCustomerProfile::class);
    }

    public function sendPasswordResetNotification(
        $token
    ): void {
        $this->notify(
            new ZigoResetPasswordNotification(
                (string) $token
            )
        );
    }
}
