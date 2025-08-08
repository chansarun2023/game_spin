<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\UserSeeder;

class SeedUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:users {--count=20 : Number of random users to create}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed users with predefined and random data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting user seeding...');

        // Run the UserSeeder
        $seeder = new UserSeeder();
        $seeder->run();

        $this->info('User seeding completed successfully!');

        return Command::SUCCESS;
    }
}
