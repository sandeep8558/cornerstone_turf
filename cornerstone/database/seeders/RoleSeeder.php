<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Define Permissions
        $permissions = [
            // User Management
            'manage_users',
            
            // Location Management
            'manage_locations',
            
            // Turf Management
            'manage_turfs',
            'view_turfs',
            
            // Slot Management
            'manage_slots',
            
            // Booking Management
            'manage_bookings',
            'create_bookings',
            'view_bookings',
            
            // Payment Management
            'manage_payments',
            'view_payments',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 2. Define Roles and Sync Permissions
        
        // Administrator: Full access
        $adminRole = Role::firstOrCreate(['name' => 'Administrator']);
        $adminRole->syncPermissions(Permission::all());

        // Manager: Operational access
        $managerRole = Role::firstOrCreate(['name' => 'Manager']);
        $managerRole->syncPermissions([
            'manage_turfs',
            'manage_slots',
            'manage_bookings',
            'view_bookings',
            'view_payments',
            'view_turfs',
        ]);

        // Client: Basic access
        $clientRole = Role::firstOrCreate(['name' => 'Client']);
        $clientRole->syncPermissions([
            'view_turfs',
            'create_bookings',
            'view_bookings',
        ]);
    }
}
