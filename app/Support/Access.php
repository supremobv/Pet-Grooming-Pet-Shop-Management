<?php

namespace App\Support;

class Access
{
    public const ROLES = ['admin', 'manager', 'receptionist', 'groomer', 'cashier', 'inventory'];

    public const PERMISSIONS = [
        'admin' => ['dashboard', 'customers', 'pets', 'appointments', 'services', 'products', 'suppliers', 'stock', 'sales', 'checkout', 'refunds', 'reports', 'settings', 'users', 'audit'],
        'manager' => ['dashboard', 'customers', 'pets', 'appointments', 'services', 'products', 'suppliers', 'stock', 'sales', 'checkout', 'refunds', 'reports', 'audit'],
        'receptionist' => ['dashboard', 'customers', 'pets', 'appointments'],
        'groomer' => ['dashboard', 'grooming'],
        'cashier' => ['dashboard', 'sales', 'checkout'],
        'inventory' => ['dashboard', 'products', 'suppliers', 'stock'],
    ];

    public static function allows(string $permission): bool
    {
        $u = auth()->user();

        return $u && $u->active && in_array($permission, self::PERMISSIONS[$u->role] ?? [], true);
    }
}
