<?php

namespace App\Support\Authorization;

final class PermissionCatalog
{
    public const GROUPS = [
        'dashboard' => ['dashboard.view'],
        'projects' => ['projects.view', 'projects.create', 'projects.update', 'projects.delete'],
        'site_reports' => ['site_reports.view', 'site_reports.create', 'site_reports.update', 'site_reports.delete', 'site_reports.approve'],
        'site_photos' => ['site_photos.view', 'site_photos.upload', 'site_photos.delete'],
        'materials' => ['materials.view', 'materials.create', 'materials.update', 'materials.delete'],
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
            'materials.view', 'materials.create', 'materials.update',
            'labor.view', 'labor.manage',
            'expenses.view', 'expenses.create',
            'departments.view',
        ],
        'client' => [
            'dashboard.view', 'projects.view', 'site_reports.view',
            'site_photos.view', 'payments.view',
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
