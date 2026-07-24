<?php

namespace Tests\Feature\Management;

use App\Models\Empleado;
use App\Models\Puesto;
use App\Models\TipoDocumentoEmpleado;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\PendingCommand;
use RuntimeException;
use Tests\TestCase;

class LegacyDataImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.legacy', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('legacy');
        $this->createLegacySchema();
    }

    public function test_it_imports_legacy_data_and_can_be_run_more_than_once(): void
    {
        $password = Hash::make('legacy-password');
        $now = now()->subYear();

        DB::connection('legacy')->table('users')->insert([
            'name' => 'Administrador anterior',
            'email' => 'admin@admin.com',
            'email_verified_at' => $now,
            'password' => $password,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::connection('legacy')->table('puestos')->insert([
            'nombre' => 'Diseñador',
            'salario_dia' => 750.50,
            'salario_quincena' => 11257.50,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::connection('legacy')->table('tipo_documentos_empleados')->insert([
            'nombre' => 'Identificación oficial',
            'frecuencia_tipo' => 'anios',
            'frecuencia_dias' => 3,
            'documentos_aceptados' => json_encode(['PDF', 'JPG'], JSON_THROW_ON_ERROR),
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::connection('legacy')->table('empleados')->insert([
            'nombre' => 'Persona Empleada',
            'correo' => 'persona@example.com',
            'curp' => 'GOCG650418HVZNML09',
            'rfc' => 'GOCG650418AB1',
            'nss' => '12345678901',
            'num_clinica_ss' => 'UMF 1',
            'puesto' => 'Diseñador',
            'estado_civil' => 'soltero',
            'sexo' => 'otro',
            'domicilio' => 'Domicilio de prueba número 123',
            'telefono' => '3221234567',
            'avatar' => null,
            'salario_dia' => 750.50,
            'salario_quincena' => 11257.50,
            'salario_vacaciones_finiquito' => null,
            'aguinaldo' => null,
            'prima_vacacional' => null,
            'dias_vacaciones' => 12,
            'dias_liquidacion' => null,
            'dias_descanso' => json_encode(['domingo'], JSON_THROW_ON_ERROR),
            'fecha_ingreso' => '2025-01-15',
            'fecha_nacimiento' => '1990-05-20',
            'fecha_contrato_siguiente' => null,
            'fecha_contrato_indefinido' => null,
            'fecha_ultimo_aviso' => null,
            'fecha_evaluacion' => null,
            'fecha_inicio_contrato' => '2025-01-15',
            'fecha_termino_contrato' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $this->legacyImportCommand()
            ->expectsTable(
                ['Recurso', 'Procesados', 'Insertados', 'Actualizados', 'Omitidos'],
                [
                    ['Usuarios', 1, 1, 0, 0],
                    ['Puestos', 1, 1, 0, 0],
                    ['Tipos de documento', 1, 1, 0, 0],
                    ['Empleados', 1, 1, 0, 0],
                ],
            )
            ->assertSuccessful();

        $administrator = User::query()->where('email', 'admin@admin.com')->firstOrFail();
        $this->assertSame($password, $administrator->password);

        $localPassword = Hash::make('locally-updated-password');
        DB::table('users')->where('id', $administrator->id)->update([
            'name' => 'Nombre actualizado localmente',
            'password' => $localPassword,
        ]);

        $this->legacyImportCommand()
            ->expectsTable(
                ['Recurso', 'Procesados', 'Insertados', 'Actualizados', 'Omitidos'],
                [
                    ['Usuarios', 1, 0, 0, 1],
                    ['Puestos', 1, 0, 0, 1],
                    ['Tipos de documento', 1, 0, 0, 1],
                    ['Empleados', 1, 0, 0, 1],
                ],
            )
            ->assertSuccessful();

        $administrator->refresh();
        $puesto = Puesto::query()->where('nombre', 'Diseñador')->firstOrFail();
        $documentType = TipoDocumentoEmpleado::query()->where('nombre', 'Identificación oficial')->firstOrFail();
        $employee = Empleado::query()->where('curp', 'GOCG650418HVZNML09')->firstOrFail();

        $this->assertSame('Nombre actualizado localmente', $administrator->name);
        $this->assertSame($localPassword, $administrator->password);
        $this->assertTrue($administrator->hasRole('Administrador'));
        $this->assertSame('750.50', $puesto->salario_dia);
        $this->assertTrue($documentType->es_renovable);
        $this->assertSame(['PDF', 'JPG'], $documentType->documentos_aceptados);
        $this->assertTrue($employee->puesto->is($puesto));
        $this->assertSame('persona', $employee->nombre_usuario);
        $this->assertSame(['domingo'], $employee->dias_descanso);

        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, Puesto::query()->count());
        $this->assertSame(1, TipoDocumentoEmpleado::query()->count());
        $this->assertSame(1, Empleado::query()->count());
    }

    public function test_a_secondary_unique_conflict_fails_and_rolls_back_the_import(): void
    {
        $existingPosition = Puesto::factory()->create(['nombre' => 'Puesto existente']);
        $existingEmployee = Empleado::factory()
            ->for($existingPosition)
            ->create(['rfc' => 'GOCG650418AB1']);

        DB::connection('legacy')->table('empleados')->insert([
            'nombre' => 'Persona en conflicto',
            'correo' => 'conflicto@example.com',
            'curp' => 'BADD110313HCMLNS09',
            'rfc' => 'GOCG650418AB1',
            'domicilio' => 'Domicilio legado',
            'telefono' => '3221234567',
            'dias_descanso' => json_encode(['domingo'], JSON_THROW_ON_ERROR),
            'fecha_nacimiento' => '1990-05-20',
        ]);

        $this->legacyImportCommand()
            ->expectsOutputToContain('Failed to import legacy table [empleados] row with ID [1].')
            ->assertFailed();

        $this->assertTrue($existingEmployee->is(Empleado::query()->sole()));
        $this->assertTrue($existingPosition->is(Puesto::query()->sole()));
        $this->assertFalse(Puesto::query()->where('nombre', 'Sin puesto')->exists());
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, TipoDocumentoEmpleado::query()->count());
    }

    public function test_it_can_restore_data_from_the_normalized_sqlite_snapshot(): void
    {
        Schema::connection('legacy')->drop('tipo_documentos_empleados');
        Schema::connection('legacy')->create('tipo_documento_empleados', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->boolean('es_renovable');
            $table->unsignedInteger('frecuencia_cantidad')->nullable();
            $table->string('frecuencia_tipo')->nullable();
            $table->json('documentos_aceptados');
            $table->boolean('activo');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::connection('legacy')->table('empleados', function (Blueprint $table): void {
            $table->string('nombre_usuario')->nullable();
            $table->unsignedBigInteger('puesto_id')->nullable();
        });

        $now = now()->subMonth();
        DB::connection('legacy')->table('users')->insert([
            'name' => 'Administrador del respaldo',
            'email' => 'admin@admin.com',
            'email_verified_at' => $now,
            'password' => Hash::make('snapshot-password'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $positionId = DB::connection('legacy')->table('puestos')->insertGetId([
            'nombre' => 'Desarrollo',
            'salario_dia' => 800,
            'salario_quincena' => 12000,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::connection('legacy')->table('tipo_documento_empleados')->insert([
            'nombre' => 'Contrato anual',
            'es_renovable' => true,
            'frecuencia_cantidad' => 1,
            'frecuencia_tipo' => 'anios',
            'documentos_aceptados' => json_encode(['PDF'], JSON_THROW_ON_ERROR),
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::connection('legacy')->table('empleados')->insert([
            'nombre' => 'Persona del respaldo',
            'nombre_usuario' => 'persona.respaldo',
            'correo' => 'persona.respaldo@example.com',
            'curp' => 'GOCG650418HVZNML09',
            'rfc' => 'GOCG650418AB1',
            'puesto_id' => $positionId,
            'estado_civil' => 'soltero',
            'sexo' => 'otro',
            'domicilio' => 'Domicilio del respaldo número 123',
            'telefono' => '3221234567',
            'dias_descanso' => json_encode(['domingo'], JSON_THROW_ON_ERROR),
            'fecha_ingreso' => '2025-01-15',
            'fecha_nacimiento' => '1990-05-20',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->legacyImportCommand()->assertSuccessful();

        $position = Puesto::query()->where('nombre', 'Desarrollo')->firstOrFail();
        $documentType = TipoDocumentoEmpleado::query()
            ->where('nombre', 'Contrato anual')
            ->firstOrFail();
        $employee = Empleado::query()->where('curp', 'GOCG650418HVZNML09')->firstOrFail();

        $this->assertTrue($employee->puesto->is($position));
        $this->assertSame('persona.respaldo', $employee->nombre_usuario);
        $this->assertTrue($documentType->es_renovable);
        $this->assertSame(1, $documentType->frecuencia_cantidad);
        $this->assertSame(['PDF'], $documentType->documentos_aceptados);
        $this->assertTrue(
            User::query()->where('email', 'admin@admin.com')->firstOrFail()->hasRole('Administrador'),
        );
    }

    private function createLegacySchema(): void
    {
        Schema::connection('legacy')->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->timestamps();
        });

        Schema::connection('legacy')->create('puestos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->float('salario_dia')->nullable();
            $table->float('salario_quincena')->nullable();
            $table->boolean('activo');
            $table->timestamps();
        });

        Schema::connection('legacy')->create('tipo_documentos_empleados', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('frecuencia_tipo')->nullable();
            $table->unsignedInteger('frecuencia_dias')->nullable();
            $table->json('documentos_aceptados')->nullable();
            $table->boolean('activo');
            $table->timestamps();
        });

        Schema::connection('legacy')->create('empleados', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('correo')->nullable();
            $table->string('curp');
            $table->string('rfc');
            $table->string('nss')->nullable();
            $table->string('num_clinica_ss')->nullable();
            $table->string('puesto')->nullable();
            $table->string('estado_civil')->nullable();
            $table->string('sexo')->nullable();
            $table->string('domicilio');
            $table->string('telefono');
            $table->longText('avatar')->nullable();
            $table->float('salario_dia')->nullable();
            $table->float('salario_quincena')->nullable();
            $table->float('salario_vacaciones_finiquito')->nullable();
            $table->float('aguinaldo')->nullable();
            $table->float('prima_vacacional')->nullable();
            $table->unsignedInteger('dias_vacaciones')->nullable();
            $table->unsignedInteger('dias_liquidacion')->nullable();
            $table->json('dias_descanso');
            $table->string('fecha_ingreso')->nullable();
            $table->string('fecha_nacimiento');
            $table->string('fecha_contrato_siguiente')->nullable();
            $table->string('fecha_contrato_indefinido')->nullable();
            $table->string('fecha_ultimo_aviso')->nullable();
            $table->string('fecha_evaluacion')->nullable();
            $table->string('fecha_inicio_contrato')->nullable();
            $table->string('fecha_termino_contrato')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function legacyImportCommand(): PendingCommand
    {
        $command = $this->artisan('app:import-legacy');

        if ($command instanceof PendingCommand) {
            return $command;
        }

        throw new RuntimeException('Console output mocking must be enabled for this test.');
    }
}
