<?php

namespace App\Domain\Network\Tenancy\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory;

    public const STATUSES = ['active', 'inactive', 'suspended'];

    protected $table = 'network_tenants';

    protected $fillable = ['name', 'slug', 'status'];

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant): void {
            $tenant->uuid ??= (string) Str::uuid();
        });
    }

    public function setSlugAttribute(string $value): void
    {
        $this->attributes['slug'] = Str::slug(Str::lower($value));
    }
}
