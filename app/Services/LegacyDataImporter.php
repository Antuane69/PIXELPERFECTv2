<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class LegacyDataImporter
{
    private const OUTCOME_INSERTED = 'inserted';

    private const OUTCOME_SKIPPED = 'skipped';

    private const OUTCOME_UPDATED = 'updated';

    private const DOCUMENT_EXTENSIONS = [
        'PDF',
        'JPG',
        'JPEG',
        'PNG',
        'WEBP',
        'DOC',
        'DOCX',
        'XLS',
        'XLSX',
    ];

    private const REQUIRED_TABLES = [
        'users',
        'puestos',
        'tipo_documentos_empleados',
        'empleados',
    ];

    /**
     * @return array{
     *     Usuarios: array{processed: int, inserted: int, updated: int, skipped: int},
     *     Puestos: array{processed: int, inserted: int, updated: int, skipped: int},
     *     'Tipos de documento': array{processed: int, inserted: int, updated: int, skipped: int},
     *     Empleados: array{processed: int, inserted: int, updated: int, skipped: int}
     * }
     */
    public function import(bool $overwrite = false): array
    {
        $this->assertLegacyConnectionIsReady();

        return DB::transaction(function () use ($overwrite): array {
            $counts = [
                'Usuarios' => $this->importUsers($overwrite),
                'Puestos' => $this->importPuestos($overwrite),
                'Tipos de documento' => $this->importDocumentTypes($overwrite),
                'Empleados' => $this->importEmployees($overwrite),
            ];

            return $counts;
        });
    }

    private function assertLegacyConnectionIsReady(): void
    {
        if (blank(config('database.connections.legacy.database'))) {
            throw new RuntimeException('Configure LEGACY_DB_DATABASE before importing legacy data.');
        }

        foreach (self::REQUIRED_TABLES as $table) {
            if (! Schema::connection('legacy')->hasTable($table)) {
                throw new RuntimeException("The legacy database is missing the [{$table}] table.");
            }
        }
    }

    /**
     * @return array{processed: int, inserted: int, updated: int, skipped: int}
     */
    private function importUsers(bool $overwrite): array
    {
        return $this->importInChunks('users', function (array $legacyUser) use ($overwrite): string {
            return $this->persist(
                'users',
                ['email' => Str::lower((string) $legacyUser['email'])],
                [
                    'name' => Str::squish((string) $legacyUser['name']),
                    'password' => (string) $legacyUser['password'],
                    'email_verified_at' => $legacyUser['email_verified_at'] ?? null,
                    'remember_token' => null,
                    'created_at' => $legacyUser['created_at'] ?? now(),
                    'updated_at' => $legacyUser['updated_at'] ?? now(),
                ],
                $overwrite,
            );
        });
    }

    /**
     * @return array{processed: int, inserted: int, updated: int, skipped: int}
     */
    private function importPuestos(bool $overwrite): array
    {
        return $this->importInChunks('puestos', function (array $legacyPuesto) use ($overwrite): string {
            return $this->persist(
                'puestos',
                ['nombre' => Str::squish((string) $legacyPuesto['nombre'])],
                [
                    'salario_dia' => $legacyPuesto['salario_dia'] ?? null,
                    'salario_quincena' => $legacyPuesto['salario_quincena'] ?? null,
                    'activo' => (bool) ($legacyPuesto['activo'] ?? false),
                    'created_at' => $legacyPuesto['created_at'] ?? now(),
                    'updated_at' => $legacyPuesto['updated_at'] ?? now(),
                    'deleted_at' => null,
                ],
                $overwrite,
            );
        });
    }

    /**
     * @return array{processed: int, inserted: int, updated: int, skipped: int}
     */
    private function importDocumentTypes(bool $overwrite): array
    {
        return $this->importInChunks('tipo_documentos_empleados', function (array $legacyType) use ($overwrite): string {
            $documents = collect($this->decodeJsonArray($legacyType['documentos_aceptados'] ?? null))
                ->map(static fn (string $extension): string => Str::upper(trim($extension)))
                ->intersect(self::DOCUMENT_EXTENSIONS)
                ->unique()
                ->values()
                ->all();
            $frequency = filled($legacyType['frecuencia_tipo'] ?? null)
                || filled($legacyType['frecuencia_dias'] ?? null);

            return $this->persist(
                'tipo_documento_empleados',
                ['nombre' => Str::squish((string) $legacyType['nombre'])],
                [
                    'es_renovable' => $frequency,
                    'frecuencia_cantidad' => $frequency
                        ? ($legacyType['frecuencia_dias'] ?? null)
                        : null,
                    'frecuencia_tipo' => $frequency
                        ? Str::lower((string) ($legacyType['frecuencia_tipo'] ?? ''))
                        : null,
                    'documentos_aceptados' => json_encode($documents, JSON_THROW_ON_ERROR),
                    'activo' => (bool) ($legacyType['activo'] ?? false),
                    'created_at' => $legacyType['created_at'] ?? now(),
                    'updated_at' => $legacyType['updated_at'] ?? now(),
                    'deleted_at' => null,
                ],
                $overwrite,
            );
        });
    }

    /**
     * @return array{processed: int, inserted: int, updated: int, skipped: int}
     */
    private function importEmployees(bool $overwrite): array
    {
        $hasUsername = Schema::connection('legacy')->hasColumn('empleados', 'nombre_usuario');

        return $this->importInChunks('empleados', function (array $legacyEmployee) use ($hasUsername, $overwrite): string {
            $legacyId = (int) $legacyEmployee['id'];
            $legacyCurp = Str::upper((string) $legacyEmployee['curp']);
            $legacyEmail = $legacyEmployee['correo'] ?? null;
            $legacyUsername = $legacyEmployee['nombre_usuario'] ?? null;
            $positionName = Str::squish((string) (($legacyEmployee['puesto'] ?? null) ?: 'Sin puesto'));
            $positionId = DB::table('puestos')->where('nombre', $positionName)->value('id');

            if ($positionId === null) {
                $positionId = DB::table('puestos')->insertGetId([
                    'nombre' => $positionName,
                    'salario_dia' => null,
                    'salario_quincena' => null,
                    'activo' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $existingUsername = DB::table('empleados')
                ->where('curp', $legacyCurp)
                ->value('nombre_usuario');
            $username = is_string($existingUsername) && $existingUsername !== ''
                ? $existingUsername
                : $this->uniqueUsername(
                    $hasUsername && is_string($legacyUsername) ? $legacyUsername : null,
                    is_string($legacyEmail) ? $legacyEmail : null,
                    $legacyId,
                );

            return $this->persist(
                'empleados',
                ['curp' => $legacyCurp],
                [
                    'nombre' => Str::squish((string) $legacyEmployee['nombre']),
                    'nombre_usuario' => $username,
                    'correo' => Str::lower((string) ($legacyEmail ?: "legacy-{$legacyId}@invalid.local")),
                    'rfc' => Str::upper((string) $legacyEmployee['rfc']),
                    'nss' => ($legacyEmployee['nss'] ?? null) ?: null,
                    'num_clinica_ss' => ($legacyEmployee['num_clinica_ss'] ?? null) ?: null,
                    'puesto_id' => $positionId,
                    'estado_civil' => ($legacyEmployee['estado_civil'] ?? null) ?: null,
                    'sexo' => ($legacyEmployee['sexo'] ?? null) ?: null,
                    'domicilio' => (string) ($legacyEmployee['domicilio'] ?? ''),
                    'telefono' => (string) ($legacyEmployee['telefono'] ?? ''),
                    'avatar' => null,
                    'salario_dia' => $legacyEmployee['salario_dia'] ?? null,
                    'salario_quincena' => $legacyEmployee['salario_quincena'] ?? null,
                    'salario_vacaciones_finiquito' => $legacyEmployee['salario_vacaciones_finiquito'] ?? null,
                    'aguinaldo' => $legacyEmployee['aguinaldo'] ?? null,
                    'prima_vacacional' => $legacyEmployee['prima_vacacional'] ?? null,
                    'dias_vacaciones' => $legacyEmployee['dias_vacaciones'] ?? null,
                    'dias_liquidacion' => $legacyEmployee['dias_liquidacion'] ?? null,
                    'dias_descanso' => json_encode(
                        $this->decodeJsonArray($legacyEmployee['dias_descanso'] ?? null),
                        JSON_THROW_ON_ERROR,
                    ),
                    'fecha_ingreso' => $this->date($legacyEmployee['fecha_ingreso'] ?? null)
                        ?? $this->date($legacyEmployee['created_at'] ?? null)
                        ?? now()->toDateString(),
                    'fecha_nacimiento' => $this->date($legacyEmployee['fecha_nacimiento'] ?? null),
                    'fecha_contrato_siguiente' => $this->date($legacyEmployee['fecha_contrato_siguiente'] ?? null),
                    'fecha_contrato_indefinido' => $this->date($legacyEmployee['fecha_contrato_indefinido'] ?? null),
                    'fecha_ultimo_aviso' => $this->date($legacyEmployee['fecha_ultimo_aviso'] ?? null),
                    'fecha_evaluacion' => $this->date($legacyEmployee['fecha_evaluacion'] ?? null),
                    'fecha_inicio_contrato' => $this->date($legacyEmployee['fecha_inicio_contrato'] ?? null),
                    'fecha_termino_contrato' => $this->date($legacyEmployee['fecha_termino_contrato'] ?? null),
                    'created_at' => $legacyEmployee['created_at'] ?? now(),
                    'updated_at' => $legacyEmployee['updated_at'] ?? now(),
                    'deleted_at' => $legacyEmployee['deleted_at'] ?? null,
                ],
                $overwrite,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $values
     * @return self::OUTCOME_INSERTED|self::OUTCOME_SKIPPED|self::OUTCOME_UPDATED
     */
    private function persist(string $table, array $identity, array $values, bool $overwrite): string
    {
        $exists = DB::table($table)->where($identity)->exists();

        if ($overwrite) {
            DB::table($table)->updateOrInsert($identity, $values);

            return $exists ? self::OUTCOME_UPDATED : self::OUTCOME_INSERTED;
        }

        if ($exists) {
            return self::OUTCOME_SKIPPED;
        }

        DB::table($table)->insert([...$identity, ...$values]);

        return self::OUTCOME_INSERTED;
    }

    /**
     * @param  callable(array<string, mixed>): (self::OUTCOME_INSERTED|self::OUTCOME_SKIPPED|self::OUTCOME_UPDATED)  $importRow
     * @return array{processed: int, inserted: int, updated: int, skipped: int}
     */
    private function importInChunks(string $table, callable $importRow): array
    {
        $counts = [
            'processed' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        $this->legacyTable($table)->chunkById(200, function ($rows) use (&$counts, $importRow, $table): void {
            foreach ($rows as $row) {
                $legacyRow = get_object_vars($row);
                $legacyId = is_scalar($legacyRow['id'] ?? null)
                    ? (string) $legacyRow['id']
                    : 'unknown';

                try {
                    $outcome = $importRow($legacyRow);
                } catch (Throwable $exception) {
                    throw new RuntimeException(
                        "Failed to import legacy table [{$table}] row with ID [{$legacyId}].",
                        previous: $exception,
                    );
                }

                $counts['processed']++;
                $counts[$outcome]++;
            }
        });

        return $counts;
    }

    private function legacyTable(string $table): Builder
    {
        return $this->legacyConnection()->table($table);
    }

    private function legacyConnection(): ConnectionInterface
    {
        return DB::connection('legacy');
    }

    private function uniqueUsername(?string $legacyUsername, ?string $email, int $legacyId): string
    {
        $base = Str::slug((string) ($legacyUsername ?: Str::before((string) $email, '@')), '_');
        $base = $base !== '' ? Str::limit($base, 50, '') : "empleado_{$legacyId}";
        $candidate = $base;
        $suffix = 1;

        while (DB::table('empleados')->where('nombre_usuario', $candidate)->exists()) {
            $candidate = Str::limit($base, 45, '').'_'.($suffix++);
        }

        return $candidate;
    }

    /**
     * @return array<int, string>
     */
    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value)));
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        try {
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        return is_array($decoded)
            ? array_values(array_filter(array_map('strval', $decoded)))
            : [];
    }

    private function date(mixed $value): ?string
    {
        if (blank($value) || $value === '0000-00-00') {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
