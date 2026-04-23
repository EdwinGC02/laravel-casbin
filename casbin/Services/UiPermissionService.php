<?php

namespace App\Casbin\Services;

use Illuminate\Support\Facades\DB;

class UiPermissionService
{
    private const ALL_ACTIONS = ['view', 'create', 'edit', 'delete', 'print', 'duplicate', 'cancel'];

    public function can(int $userId, int|string $tenantId, string $module, string $action): bool
    {
        if (! config('casbin.enabled', true)) {
            return true;
        }

        $domain = config('casbin.tenant_prefix', 'tenant:') . $tenantId;
        $conn = DB::connection(config('casbin.connection', 'landlord'));

        return $conn->table('casbin_rule as p')
            ->join('casbin_rule as g', function ($join) use ($userId, $domain) {
                $join->on('g.v1', '=', 'p.v0')
                    ->where('g.ptype', 'g')
                    ->where('g.v0', 'user:' . $userId)
                    ->where('g.v2', $domain);
            })
            ->where('p.ptype', 'p')
            ->where('p.v1', $domain)
            ->where(function ($q) use ($module) {
                $q->where('p.v2', $module)->orWhere('p.v2', '*');
            })
            ->where(function ($q) use ($action) {
                $q->where('p.v3', $action)->orWhere('p.v3', '*');
            })
            ->exists();
    }

    public function modulePermissions(int $userId, int|string $tenantId, string $module): array
    {
        if (! config('casbin.enabled', true)) {
            return array_fill_keys(self::ALL_ACTIONS, true);
        }

        return [
            'view' => $this->can($userId, $tenantId, $module, 'view'),
            'create' => $this->can($userId, $tenantId, $module, 'create'),
            'edit' => $this->can($userId, $tenantId, $module, 'edit'),
            'delete' => $this->can($userId, $tenantId, $module, 'delete'),
            'print' => $this->can($userId, $tenantId, $module, 'print'),
            'duplicate' => $this->can($userId, $tenantId, $module, 'duplicate'),
            'cancel' => $this->can($userId, $tenantId, $module, 'cancel'),
        ];
    }

    public function viewableModules(int $userId, int|string $tenantId): array
    {
        if (! config('casbin.enabled', true)) {
            return [];
        }

        $domain = config('casbin.tenant_prefix', 'tenant:') . $tenantId;
        $conn = DB::connection(config('casbin.connection', 'landlord'));

        return $conn->table('casbin_rule as p')
            ->join('casbin_rule as g', function ($join) use ($userId, $domain) {
                $join->on('g.v1', '=', 'p.v0')
                    ->where('g.ptype', 'g')
                    ->where('g.v0', 'user:' . $userId)
                    ->where('g.v2', $domain);
            })
            ->where('p.ptype', 'p')
            ->where('p.v1', $domain)
            ->where(function ($q) {
                $q->where('p.v3', 'view')->orWhere('p.v3', '*');
            })
            ->distinct()
            ->pluck('p.v2')
            ->values()
            ->all();
    }
}
