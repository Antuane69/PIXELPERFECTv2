<?php

namespace Tests\Feature\Multiempresa;

use App\EstadoEmpresa;
use App\Models\Empresa;
use App\Models\GrupoEmpresarial;
use App\Models\Role;
use App\Models\User;
use App\TipoGrupoEmpresarial;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmpresaManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_only_platform_superadministrator_can_list_and_create_companies(): void
    {
        $platformAdministrator = User::factory()->superadministradorPlataforma()->create();
        $companyAdministrator = User::factory()->create();
        $companyAdministrator->assignRole(Role::findByName('Administrador', 'web'));

        $this->actingAs($companyAdministrator)
            ->get(route('platform.empresas.index'))
            ->assertForbidden();

        $this->actingAs($companyAdministrator)
            ->post(route('platform.empresas.store'), $this->validCompanyData())
            ->assertForbidden();

        $this->actingAs($platformAdministrator)
            ->get(route('platform.empresas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/empresas/index')
                ->has('empresasPaginadas.data', 1)
                ->has('grupos', 1)
                ->has('empresas.disponibles', 1)
                ->where('empresas.activa', null),
            );
    }

    public function test_independent_company_is_created_with_exclusive_group(): void
    {
        $platformAdministrator = User::factory()->superadministradorPlataforma()->create();

        $this->actingAs($platformAdministrator)
            ->post(route('platform.empresas.store'), $this->validCompanyData([
                'nombre_legal' => '  Acme   México SA de CV  ',
                'nombre_comercial' => '  Acme   México  ',
                'rfc' => '  abc010203xy9 ',
            ]))
            ->assertRedirect(route('platform.empresas.index'))
            ->assertSessionHasNoErrors();

        $empresa = Empresa::query()->where('nombre_legal', 'Acme México SA de CV')->firstOrFail();

        $this->assertSame('Acme México SA de CV', $empresa->nombre_legal);
        $this->assertSame('Acme México', $empresa->nombre_comercial);
        $this->assertSame('ABC010203XY9', $empresa->rfc);
        $this->assertSame('52', $empresa->codigo_pais_contacto);
        $this->assertSame('5555555555', $empresa->telefono_contacto);
        $this->assertSame(EstadoEmpresa::Activa, $empresa->estado);
        $this->assertNotNull($empresa->activada_at);
        $this->assertSame(TipoGrupoEmpresarial::Individual, $empresa->grupoEmpresarial->tipo);
        $this->assertSame('Acme México', $empresa->grupoEmpresarial->nombre);
    }

    public function test_company_can_join_existing_group_and_group_becomes_corporate(): void
    {
        $platformAdministrator = User::factory()->superadministradorPlataforma()->create();
        $group = GrupoEmpresarial::factory()->create();
        Empresa::factory()->activa()->for($group, 'grupoEmpresarial')->create();

        $this->actingAs($platformAdministrator)
            ->post(route('platform.empresas.store'), $this->validCompanyData([
                'nombre_legal' => 'Segunda empresa SA de CV',
                'grupo_empresarial_id' => (string) $group->id,
            ]))
            ->assertRedirect(route('platform.empresas.index'));

        $empresa = Empresa::query()->where('nombre_legal', 'Segunda empresa SA de CV')->firstOrFail();

        $this->assertSame($group->id, $empresa->grupo_empresarial_id);
        $this->assertSame(TipoGrupoEmpresarial::Corporativo, $group->fresh()?->tipo);
    }

    public function test_demo_requires_future_expiration(): void
    {
        $platformAdministrator = User::factory()->superadministradorPlataforma()->create();

        $this->actingAs($platformAdministrator)
            ->post(route('platform.empresas.store'), $this->validCompanyData([
                'estado' => EstadoEmpresa::Demo->value,
                'demo_ends_at' => null,
            ]))
            ->assertSessionHasErrors('demo_ends_at');

        $this->actingAs($platformAdministrator)
            ->post(route('platform.empresas.store'), $this->validCompanyData([
                'nombre_legal' => 'Demo válida SA de CV',
                'estado' => EstadoEmpresa::Demo->value,
                'demo_ends_at' => now()->addWeek()->toDateTimeString(),
            ]))
            ->assertRedirect(route('platform.empresas.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Empresa::query()->count());
    }

    public function test_company_requires_a_valid_twelve_character_legal_entity_rfc(): void
    {
        $platformAdministrator = User::factory()->superadministradorPlataforma()->create();

        foreach (['ABC010232XY9', 'AB010203XY9', 'ABCD010203XY9'] as $rfc) {
            $this->actingAs($platformAdministrator)
                ->post(route('platform.empresas.store'), $this->validCompanyData(['rfc' => $rfc]))
                ->assertSessionHasErrors('rfc');
        }

        $this->assertSame(1, Empresa::query()->count());
    }

    public function test_company_accepts_a_legal_entity_rfc_with_a_valid_constitution_date(): void
    {
        $platformAdministrator = User::factory()->superadministradorPlataforma()->create();

        $this->actingAs($platformAdministrator)
            ->post(route('platform.empresas.store'), $this->validCompanyData([
                'rfc' => 'NAG020213GR5',
            ]))
            ->assertRedirect(route('platform.empresas.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'NAG020213GR5',
            Empresa::query()->where('rfc', 'NAG020213GR5')->firstOrFail()->rfc,
        );
    }

    public function test_company_cannot_start_as_expired_or_disabled(): void
    {
        $platformAdministrator = User::factory()->superadministradorPlataforma()->create();

        foreach ([EstadoEmpresa::Vencida, EstadoEmpresa::Desactivada] as $state) {
            $this->actingAs($platformAdministrator)
                ->post(route('platform.empresas.store'), $this->validCompanyData(['estado' => $state->value]))
                ->assertSessionHasErrors('estado');
        }

        $this->assertSame(1, Empresa::query()->count());
    }

    public function test_company_phone_requires_digits_and_paired_country_code(): void
    {
        $platformAdministrator = User::factory()->superadministradorPlataforma()->create();

        $this->actingAs($platformAdministrator)
            ->post(route('platform.empresas.store'), $this->validCompanyData([
                'codigo_pais_contacto' => '52',
                'telefono_contacto' => '555ABC5555',
            ]))
            ->assertSessionHasErrors('telefono_contacto');

        $this->actingAs($platformAdministrator)
            ->post(route('platform.empresas.store'), $this->validCompanyData([
                'codigo_pais_contacto' => null,
            ]))
            ->assertSessionHasErrors('codigo_pais_contacto');
    }

    public function test_company_logo_is_compressed_and_stored_as_binary_data(): void
    {
        $platformAdministrator = User::factory()->superadministradorPlataforma()->create();
        $logo = UploadedFile::fake()->image('logo.png', 640, 320);

        $this->actingAs($platformAdministrator)
            ->post(route('platform.empresas.store'), $this->validCompanyData([
                'logo' => $logo,
            ]))
            ->assertRedirect(route('platform.empresas.index'))
            ->assertSessionHasNoErrors();

        $empresa = Empresa::query()->where('nombre_legal', 'Empresa ejemplo SA de CV')->firstOrFail();

        $this->assertIsString($empresa->logo);
        $this->assertNotSame('', $empresa->logo);
        $this->assertSame('image/png', $empresa->logo_mime_type);

        $decodedLogo = imagecreatefromstring($empresa->logo);

        $this->assertInstanceOf(\GdImage::class, $decodedLogo);
        imagedestroy($decodedLogo);

        $this->actingAs($platformAdministrator)
            ->get(route('platform.empresas.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('empresasPaginadas.data.0.logo_url', static fn (mixed $value): bool => is_string($value)
                    && str_starts_with($value, 'data:image/png;base64,'))
                ->where('empresasPaginadas.data.1.logo_url', null));
    }

    public function test_company_logo_must_be_a_supported_image(): void
    {
        $platformAdministrator = User::factory()->superadministradorPlataforma()->create();

        $this->actingAs($platformAdministrator)
            ->post(route('platform.empresas.store'), $this->validCompanyData([
                'logo' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('logo');

        $this->assertSame(1, Empresa::query()->count());
    }

    public function test_database_rejects_company_without_business_group(): void
    {
        $this->expectException(QueryException::class);

        Empresa::query()->create([
            'nombre_legal' => 'Sin grupo SA de CV',
            'slug' => 'sin-grupo',
            'estado' => EstadoEmpresa::Prospecto,
        ]);
    }

    public function test_company_listing_filters_and_preserves_query_on_multiple_pages(): void
    {
        $platformAdministrator = User::factory()->superadministradorPlataforma()->create();
        $group = GrupoEmpresarial::factory()->create(['nombre' => 'Grupo Norte']);
        Empresa::factory()
            ->count(12)
            ->for($group, 'grupoEmpresarial')
            ->create([
                'nombre_legal' => 'Empresa Norte SA de CV',
                'estado' => EstadoEmpresa::Activa,
            ]);
        Empresa::factory()->count(4)->create();

        $this->actingAs($platformAdministrator)
            ->get(route('platform.empresas.index', [
                'search' => 'Norte',
                'estado' => EstadoEmpresa::Activa->value,
                'grupo_empresarial_id' => (string) $group->id,
                'per_page' => 5,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'Norte')
                ->where('filters.estado', EstadoEmpresa::Activa->value)
                ->where('filters.grupoEmpresarialId', $group->id)
                ->where('empresasPaginadas.current_page', 2)
                ->where('empresasPaginadas.per_page', 5)
                ->where('empresasPaginadas.total', 12)
                ->where('empresasPaginadas.links', fn (mixed $links): bool => collect($links)->contains(
                    fn (mixed $link): bool => is_array($link)
                        && $this->urlContainsQuery($link['url'] ?? null, [
                            'search' => 'Norte',
                            'estado' => EstadoEmpresa::Activa->value,
                            'grupo_empresarial_id' => (string) $group->id,
                            'per_page' => 5,
                        ]),
                )),
            );
    }

    public function test_initial_company_seeding_is_idempotent_and_assigns_platform_administrator(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $empresa = Empresa::query()->where('slug', 'pixel-perfect')->firstOrFail();
        $administrator = User::query()
            ->where('email', 'admin@pixelperfect.local')
            ->firstOrFail();

        $this->assertSame(1, GrupoEmpresarial::query()->where('slug', 'pixel-perfect')->count());
        $this->assertSame(1, Empresa::query()->where('slug', 'pixel-perfect')->count());
        $this->assertTrue($administrator->es_superadministrador_plataforma);
        $this->assertTrue($administrator->empresas()->whereKey($empresa->id)->exists());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validCompanyData(array $overrides = []): array
    {
        return [
            'nombre_legal' => 'Empresa ejemplo SA de CV',
            'nombre_comercial' => 'Empresa ejemplo',
            'grupo_empresarial_id' => null,
            'rfc' => 'ABC010203XY9',
            'correo_contacto' => 'contacto@example.com',
            'codigo_pais_contacto' => '52',
            'telefono_contacto' => '5555555555',
            'zona_horaria' => 'America/Mexico_City',
            'moneda' => 'MXN',
            'estado' => EstadoEmpresa::Activa->value,
            'demo_ends_at' => null,
            ...$overrides,
        ];
    }
}
