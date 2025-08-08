<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create an admin user if it doesn't exist
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'username' => 'admin',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
                'agent_id' => 'admin_agent',
                'status' => true,
                'role_id' => 6, // Admin role
                'last_login_at' => now(),
            ]
        );

        // Create a test user if it doesn't exist
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'username' => 'testuser',
                'password' => Hash::make('test123'),
                'email_verified_at' => now(),
                'agent_id' => 'test_agent',
                'status' => true,
                'role_id' => 6, // Regular user role
                'last_login_at' => now()->subDays(6),
            ]
        );

        // Create users with specific usernames for testing if they don't exist
        User::firstOrCreate(
            ['email' => 'john@example.com'],
            [
                'name' => 'John Doe',
                'username' => 'johndoe',
                'password' => Hash::make('123456'),
                'email_verified_at' => now(),
                'agent_id' => 'john_agent',
                'status' => true,
                'role_id' => 6,
                'last_login_at' => now()->subHours(5),
            ]
        );

        User::firstOrCreate(
            ['email' => 'jane@example.com'],
            [
                'name' => 'Jane Smith',
                'username' => 'janesmith',
                'password' => Hash::make('123456'),
                'email_verified_at' => now(),
                'agent_id' => 'jane_agent',
                'status' => true,
                'role_id' => 6,
                'last_login_at' => now()->subDays(1),
            ]
        );

        // Create a moderator user
        User::firstOrCreate(
            ['email' => 'moderator@example.com'],
            [
                'name' => 'Moderator User',
                'username' => 'moderator',
                'password' => Hash::make('mod123'),
                'email_verified_at' => now(),
                'agent_id' => 'mod_agent',
                'status' => true,
                'role_id' => 3, // Moderator role
                'last_login_at' => now()->subHours(12),
            ]
        );

        // Create an inactive user for testing
        User::firstOrCreate(
            ['email' => 'inactive@example.com'],
            [
                'name' => 'Inactive User',
                'username' => 'inactiveuser',
                'password' => Hash::make('inactive123'),
                'email_verified_at' => now(),
                'agent_id' => 'inactive_agent',
                'status' => false, // Inactive user
                'role_id' => 6,
                'last_login_at' => now()->subWeeks(6),
            ]
        );

        // Create 20 random users using factory (only if total users are less than 30)
        if (User::count() < 30) {
            User::factory(20)->create([
                'status' => true,
                'role_id' => 6, // Regular user role
                'last_login_at' => fake()->optional(0.7)->dateTimeBetween('-30 days', 'now'),
            ]);
        }

        // Create some users with different roles
        $roleUsers = [
            [
                'name' => 'Manager User',
                'username' => 'manager',
                'email' => 'manager@example.com',
                'password' => 'manager123',
                'role_id' => 4, // Manager role
            ],
            [
                'name' => 'Supervisor User',
                'username' => 'supervisor',
                'email' => 'supervisor@example.com',
                'password' => 'super123',
                'role_id' => 5, // Supervisor role
            ],
        ];

        foreach ($roleUsers as $userData) {
            User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'username' => $userData['username'],
                    'password' => Hash::make($userData['password']),
                    'email_verified_at' => now(),
                    'agent_id' => $userData['username'] . '_agent',
                    'status' => true,
                    'role_id' => $userData['role_id'],
                    'last_login_at' => fake()->optional(0.8)->dateTimeBetween('-7 days', 'now'),
                ]
            );
        }

        $this->command->info('User seeding completed successfully!');
        $this->command->info('Total users created: ' . User::count());
    }
}
