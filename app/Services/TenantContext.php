<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\App;

class TenantContext
{
    protected static ?Tenant $tenant = null;
    protected static ?int $tenantId = null;
    protected static bool $bypassed = false;
    protected static bool $resolvingFromAuth = false;

    public static function set(?Tenant $tenant): void
    {
        static::$tenant = $tenant;
        static::$tenantId = $tenant?->id;

        if ($tenant) {
            App::instance('current_tenant', $tenant);
            App::instance('current_tenant_id', $tenant->id);
        } else {
            static::reset();
        }
    }

    public static function setById(?int $id): void
    {
        if (! $id) {
            static::reset();
            return;
        }

        static::$tenantId = $id;
        App::instance('current_tenant_id', $id);

        if (! static::$tenant || static::$tenant->id !== $id) {
            static::$tenant = Tenant::find($id);
            if (static::$tenant) {
                App::instance('current_tenant', static::$tenant);
            }
        }
    }

    public static function get(): ?Tenant
    {
        if (static::$tenant) {
            return static::$tenant;
        }

        $id = static::getId();
        if ($id) {
            static::$tenant = Tenant::find($id);
            if (static::$tenant) {
                App::instance('current_tenant', static::$tenant);
            }

            return static::$tenant;
        }

        return null;
    }

    public static function getId(): ?int
    {
        if (static::$bypassed || App::bound('bypass_tenant_scope')) {
            return null;
        }

        if (static::$tenantId) {
            return static::$tenantId;
        }

        if (App::bound('current_tenant_id')) {
            return (int) App::make('current_tenant_id');
        }

        if (static::$resolvingFromAuth) {
            return null;
        }

        static::$resolvingFromAuth = true;

        try {
            if (auth()->check() && auth()->user()?->tenant_id) {
                static::$tenantId = (int) auth()->user()->tenant_id;

                return static::$tenantId;
            }
        } finally {
            static::$resolvingFromAuth = false;
        }

        return null;
    }

    public static function bypass(): void
    {
        static::$bypassed = true;
    }

    public static function unbypass(): void
    {
        static::$bypassed = false;
    }

    public static function isBypassed(): bool
    {
        return static::$bypassed || App::bound('bypass_tenant_scope');
    }

    public static function withoutScope(callable $callback)
    {
        $previous = static::$bypassed;
        static::$bypassed = true;

        try {
            return $callback();
        } finally {
            static::$bypassed = $previous;
        }
    }

    public static function reset(): void
    {
        static::$tenant = null;
        static::$tenantId = null;
        static::$bypassed = false;
        static::$resolvingFromAuth = false;

        static::forgetBindings();
    }

    protected static function forgetBindings(): void
    {
        if (App::bound('current_tenant')) {
            App::forgetInstance('current_tenant');
        }

        if (App::bound('current_tenant_id')) {
            App::forgetInstance('current_tenant_id');
        }
    }
}
