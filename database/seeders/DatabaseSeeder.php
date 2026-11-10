<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoCatalogSeeder::class,
            DemoContentSeeder::class,
            AiConfigSeeder::class,
            AnalysisSeeder::class,
        ]);

        // A known admin exists only on local machines. Elsewhere use `php artisan app:create-admin`.
        if (app()->environment('local')) {
            $admin = User::firstOrNew(['email' => 'admin@example.com']);
            $admin->fill(['name' => 'Local Admin', 'password' => 'password']);
            $admin->is_admin = true;
            $admin->save();
        }
    }
}
