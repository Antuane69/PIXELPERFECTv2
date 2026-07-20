<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $administrator = User::query()->updateOrCreate(
                ['email' => 'admin@pixelperfect.local'],
                [
                    'name' => 'Administrador',
                    'password' => 'password',
                    'email_verified_at' => now(),
                ],
            );

            $administrator->syncRoles('Administrador');
        }
    }
}
