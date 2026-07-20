<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LegacyDataImporter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:import-legacy
    {--admin-email=admin@admin.com : Existing imported user who should receive the Administrator role}
    {--overwrite : Overwrite matching target records with legacy values}
    {--force : Run without a production confirmation prompt}')]
#[Description('Import users and human-resources data from the legacy Pixel Perfect database')]
class ImportLegacyData extends Command
{
    public function handle(LegacyDataImporter $importer): int
    {
        if (app()->isProduction() && ! $this->option('force') && ! $this->confirm('Import legacy data into the production database?')) {
            $this->components->warn('Import cancelled.');

            return self::FAILURE;
        }

        try {
            $counts = $importer->import((bool) $this->option('overwrite'));
        } catch (\Throwable $exception) {
            report($exception);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->call('db:seed', [
            '--class' => RolesAndPermissionsSeeder::class,
            '--force' => true,
        ]);

        $administratorEmail = (string) $this->option('admin-email');
        $administrator = filter_var($administratorEmail, FILTER_VALIDATE_EMAIL)
            ? User::query()->where('email', $administratorEmail)->first()
            : null;

        if ($administrator === null) {
            $this->components->warn(
                "No se asignó el rol Administrador porque no existe el usuario [{$administratorEmail}].",
            );
        } else {
            $administrator->syncRoles(['Administrador']);
        }

        $this->table(
            ['Recurso', 'Procesados', 'Insertados', 'Actualizados', 'Omitidos'],
            collect($counts)->map(fn (array $count, string $resource): array => [
                $resource,
                $count['processed'],
                $count['inserted'],
                $count['updated'],
                $count['skipped'],
            ])->values()->all(),
        );

        $this->components->info('Legacy data imported successfully.');

        return self::SUCCESS;
    }
}
