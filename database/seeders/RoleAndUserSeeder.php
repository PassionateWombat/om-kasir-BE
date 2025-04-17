<?php

namespace Database\Seeders;

use App\Models\User;
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
        $freeTier = Role::firstOrCreate(['name' => 'free']);
        $premiumTier = Role::firstOrCreate(['name' => 'premium']);

        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@admin'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('admin'),
            ]
        );
        $admin->assignRole($adminRole);

        // Create normal user
        $user = User::firstOrCreate(
            ['email' => 'user@user'],
            [
                'name' => 'Normal User',
                'password' => Hash::make('user'),
            ]
        );
        $user->assignRole($userRole);
        $user->assignRole($freeTier);

        $user = User::firstOrCreate(
            ['email' => 'premium@user'],
            [
                'name' => 'Premium User',
                'password' => Hash::make('user'),
            ]
        );
        $user->assignRole($userRole);
        $user->assignRole($premiumTier);
    }
}
