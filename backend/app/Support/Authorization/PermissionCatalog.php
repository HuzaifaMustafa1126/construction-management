<?php

namespace App\Support\Authorization;

final class PermissionCatalog
{
    public const GROUPS = [
        'dashboard' => ['dashboard.view'],
        'projects' => ['projects.view', 'projects.create', 'projects.update', 'projects.delete', 'projects.assign', 'projects.budget.view'],
        'site_reports' => ['site_reports.view', 'site_reports.create', 'site_reports.update', 'site_reports.delete', 'site_reports.approve'],
        'site_photos' => ['site_photos.view', 'site_photos.upload', 'site_photos.delete'],
        'photos' => ['photos.view', 'photos.create', 'photos.update', 'photos.delete', 'photos.approve'],
        'materials' => ['materials.view', 'materials.create', 'materials.update', 'materials.delete', 'material_categories.manage', 'measurement_units.manage'],
        'inventory' => ['inventory.view', 'inventory.create', 'inventory.update', 'inventory.opening_stock'],
        'suppliers' => ['suppliers.view', 'suppliers.manage'],
        'material_deliveries' => ['material_deliveries.view', 'material_deliveries.create', 'material_deliveries.update', 'material_deliveries.receive', 'material_deliveries.cancel'],
        'material_consumptions' => ['material_consumptions.view', 'material_consumptions.create', 'material_consumptions.update', 'material_consumptions.post', 'material_consumptions.cancel'],
        'stock_adjustments' => ['stock_adjustments.view', 'stock_adjustments.create', 'stock_adjustments.update', 'stock_adjustments.post', 'stock_adjustments.cancel'],
        'inventory_reports' => ['inventory_reports.view', 'inventory_reports.view_company', 'inventory_reports.view_movements', 'inventory_reports.view_alerts'],
        'labor' => ['labor.view', 'labor.manage'],
        'expenses' => ['expenses.view', 'expenses.create', 'expenses.update', 'expenses.delete'],
        'payments' => ['payments.view', 'payments.manage'],
        'users' => ['users.view', 'users.create', 'users.update', 'users.delete'],
        'roles' => ['roles.view', 'roles.create', 'roles.update', 'roles.delete', 'roles.assign'],
        'company' => ['company.view', 'company.update'],
        'departments' => ['departments.view', 'departments.create', 'departments.update', 'departments.delete', 'departments.assign'],
    ];

    public const ROLE_PERMISSIONS = [
        'company_admin' => '*',
        'site_supervisor' => [
            'dashboard.view', 'projects.view',
            'site_reports.view', 'site_reports.create', 'site_reports.update',
            'site_photos.view', 'site_photos.upload',
            'photos.view', 'photos.create', 'photos.update',
            'materials.view', 'materials.create', 'materials.update',
            'inventory.view',
            'suppliers.view', 'material_deliveries.view', 'material_consumptions.view',
            'stock_adjustments.view', 'stock_adjustments.create',
            'inventory_reports.view', 'inventory_reports.view_movements', 'inventory_reports.view_alerts',
            'labor.view', 'labor.manage',
            'expenses.view', 'expenses.create',
            'departments.view',
        ],
        'client' => [
            'dashboard.view', 'projects.view', 'site_reports.view',
            'site_photos.view', 'payments.view',
            'photos.view',
        ],
    ];

    public static function all(): array
    {
        return array_values(array_merge(...array_values(self::GROUPS)));
    }

    public static function forRole(string $role): array
    {
        $permissions = self::ROLE_PERMISSIONS[$role] ?? [];

        return $permissions === '*' ? self::all() : $permissions;
    }
}
