<?php

namespace App\Support\Authorization;

final class PermissionCatalog
{
    /** Exact semantic equivalents, retained for backwards-compatible authorization. */
    public const LEGACY_TO_CANONICAL = [
        'projects.update' => 'projects.edit',
        'site_reports.view' => 'daily_reports.view',
        'site_reports.create' => 'daily_reports.create',
        'site_reports.approve' => 'daily_reports.approve',
    ];

    /** Stored permissions with no defined action contract. They are preserved, but not newly selectable. */
    public const RESERVED = ['users.manage', 'company.settings'];

    public const GROUPS = [
        'dashboard' => ['dashboard.view'],
        'projects' => ['projects.view', 'projects.create', 'projects.edit', 'projects.delete', 'projects.assign', 'projects.budget.view'],
        'tasks' => ['tasks.view', 'tasks.create', 'tasks.assign', 'tasks.complete'],
        'daily_reports' => ['daily_reports.view', 'daily_reports.create', 'daily_reports.approve'],
        'daily_report_management' => ['site_reports.update', 'site_reports.delete'],
        'site_photos' => ['site_photos.view', 'site_photos.upload', 'site_photos.delete'],
        'photos' => ['photos.view', 'photos.create', 'photos.update', 'photos.delete', 'photos.approve'],
        'workers' => ['workers.view', 'workers.manage', 'workers.mark_attendance'],
        'materials' => ['materials.view', 'materials.request', 'materials.approve_request', 'materials.issue', 'materials.adjust', 'materials.create', 'materials.update', 'materials.delete', 'material_categories.manage', 'measurement_units.manage'],
        'inventory' => ['inventory.view', 'inventory.create', 'inventory.update', 'inventory.opening_stock'],
        'suppliers' => ['suppliers.view', 'suppliers.manage'],
        'material_deliveries' => ['material_deliveries.view', 'material_deliveries.create', 'material_deliveries.update', 'material_deliveries.receive', 'material_deliveries.cancel'],
        'material_consumptions' => ['material_consumptions.view', 'material_consumptions.create', 'material_consumptions.update', 'material_consumptions.post', 'material_consumptions.cancel'],
        'stock_adjustments' => ['stock_adjustments.view', 'stock_adjustments.create', 'stock_adjustments.update', 'stock_adjustments.post', 'stock_adjustments.cancel'],
        'inventory_reports' => ['inventory_reports.view', 'inventory_reports.view_company', 'inventory_reports.view_movements', 'inventory_reports.view_alerts'],
        'material_requests' => ['material_requests.view', 'material_requests.create', 'material_requests.update', 'material_requests.submit', 'material_requests.approve', 'material_requests.issue', 'material_requests.return', 'inventory.transfer'],
        'labor' => ['labor.view', 'labor.manage'],
        'expenses' => ['expenses.view', 'expenses.create', 'expenses.approve', 'expenses.update', 'expenses.delete'],
        'issues' => ['issues.view', 'issues.create', 'issues.resolve'],
        'documents' => ['documents.view', 'documents.upload', 'documents.delete'],
        'payments' => ['payments.view', 'payments.manage'],
        'users' => ['users.view', 'users.create', 'users.update', 'users.delete'],
        'roles' => ['roles.view', 'roles.create', 'roles.update', 'roles.delete', 'roles.assign'],
        'company' => ['company.view', 'company.update'],
        'departments' => ['departments.view', 'departments.create', 'departments.update', 'departments.delete', 'departments.assign'],
    ];

    public const ROLE_PERMISSIONS = [
        'company_admin' => '*',
        'project_manager' => [
            'dashboard.view',
            'projects.view', 'projects.create', 'projects.edit', 'projects.delete', 'projects.assign', 'projects.budget.view',
            'tasks.view', 'tasks.create', 'tasks.assign', 'tasks.complete',
            'daily_reports.view', 'daily_reports.approve',
            'workers.view', 'workers.manage', 'workers.mark_attendance', 'labor.view', 'labor.manage',
            'materials.view', 'materials.approve_request', 'material_requests.view', 'material_requests.approve',
            'expenses.view', 'expenses.approve',
            'documents.view', 'documents.upload', 'documents.delete',
        ],
        'site_engineer' => [
            'dashboard.view', 'projects.view',
            'tasks.view', 'tasks.create', 'tasks.complete',
            'daily_reports.view', 'daily_reports.create', 'site_reports.update',
            'workers.view', 'workers.manage', 'labor.view', 'labor.manage',
            'materials.view', 'materials.request', 'material_requests.view', 'material_requests.create', 'material_requests.update', 'material_requests.submit',
            'expenses.view', 'expenses.create',
            'documents.view', 'documents.upload',
        ],
        'site_supervisor' => [
            'dashboard.view', 'projects.view',
            'tasks.view', 'tasks.create', 'tasks.complete',
            'daily_reports.view', 'daily_reports.create', 'site_reports.update',
            'workers.view', 'workers.manage', 'labor.view', 'labor.manage',
            'materials.view', 'materials.request', 'material_requests.view', 'material_requests.create', 'material_requests.update', 'material_requests.submit',
            'documents.view',
            'site_photos.view', 'site_photos.upload', 'photos.view', 'photos.create', 'photos.update',
        ],
        'quantity_surveyor' => [
            'dashboard.view', 'projects.view', 'projects.budget.view',
            'tasks.view', 'daily_reports.view',
            'workers.view', 'labor.view',
            'materials.view', 'materials.request', 'material_requests.view', 'material_requests.create', 'material_requests.update', 'material_requests.submit',
            'expenses.view', 'expenses.create',
            'documents.view', 'documents.upload',
        ],
        'accountant' => [
            'dashboard.view', 'projects.view', 'daily_reports.view',
            'workers.view', 'labor.view', 'materials.view',
            'expenses.view', 'expenses.approve', 'payments.view', 'payments.manage',
            'documents.view',
        ],
        'store_keeper' => [
            'dashboard.view', 'projects.view', 'daily_reports.view',
            'materials.view', 'materials.request', 'materials.approve_request', 'materials.issue', 'materials.adjust',
            'materials.create', 'materials.update', 'materials.delete', 'material_categories.manage', 'measurement_units.manage',
            'inventory.view', 'inventory.create', 'inventory.update', 'inventory.opening_stock',
            'suppliers.view', 'suppliers.manage',
            'material_deliveries.view', 'material_deliveries.create', 'material_deliveries.update', 'material_deliveries.receive', 'material_deliveries.cancel',
            'material_consumptions.view', 'material_consumptions.create', 'material_consumptions.update', 'material_consumptions.post', 'material_consumptions.cancel',
            'stock_adjustments.view', 'stock_adjustments.create', 'stock_adjustments.update', 'stock_adjustments.post', 'stock_adjustments.cancel',
            'inventory_reports.view', 'inventory_reports.view_movements', 'inventory_reports.view_alerts',
            'material_requests.view', 'material_requests.create', 'material_requests.update', 'material_requests.submit', 'material_requests.approve', 'material_requests.issue', 'material_requests.return', 'inventory.transfer',
            'documents.view',
        ],
        'client' => [
            'dashboard.view', 'projects.view', 'daily_reports.view',
            'documents.view', 'site_photos.view', 'photos.view',
        ],
        'worker' => ['tasks.view'],
    ];

    public static function all(): array
    {
        return array_values(array_merge(...array_values(self::GROUPS)));
    }

    public static function definitions(): array
    {
        return array_values(array_unique([
            ...self::all(),
            ...array_keys(self::LEGACY_TO_CANONICAL),
            ...self::RESERVED,
        ]));
    }

    public static function canonical(string $permission): string
    {
        return self::LEGACY_TO_CANONICAL[$permission] ?? $permission;
    }

    public static function equivalents(string $permission): array
    {
        $canonical = self::canonical($permission);
        $legacy = array_keys(self::LEGACY_TO_CANONICAL, $canonical, true);

        return array_values(array_unique([$canonical, ...$legacy]));
    }

    public static function normalize(iterable $permissions): array
    {
        return collect($permissions)
            ->map(fn ($permission) => self::canonical(is_string($permission) ? $permission : $permission->name))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public static function isSelectable(string $permission): bool
    {
        return in_array($permission, self::all(), true);
    }

    public static function forRole(string $role): array
    {
        $permissions = self::ROLE_PERMISSIONS[$role] ?? [];

        return $permissions === '*' ? self::all() : $permissions;
    }
}
