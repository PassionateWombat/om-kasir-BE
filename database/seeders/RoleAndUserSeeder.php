<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RoleAndUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('admin'),
            ]
        );
        $admin->assignRole($adminRole);

        // Create normal user
        $user = User::firstOrCreate(
            ['email' => 'user@user.com'],
            [
                'name' => 'Normal User',
                'password' => Hash::make('user'),
            ]
        );
        $user->assignRole($userRole);

        $user = User::firstOrCreate(
            ['email' => 'premium@user.com'],
            [
                'name' => 'Premium User',
                'password' => Hash::make('user'),
                'premium_until' => Carbon::now()->addMonths(1),  // Example: "Premium User" until next month
            ]
        );
        $user->assignRole($userRole);
    }
}
