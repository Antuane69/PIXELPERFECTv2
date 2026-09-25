<?php

namespace Tests\Feature\Management;

use App\Actions\Empleados\RenderEmpleadoDocumentoPdf;
use App\Models\Empleado;
use App\Models\EmpleadoCarpeta;
use App\Models\EmpleadoDocumentoCatalogo;
use App\Models\Empresa;
use App\Models\MembresiaEmpresa;
use App\Models\Modulo;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class EmpleadoDocumentoCatalogoManagementTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $usuario;

    private Empleado $empleado;

    private EmpleadoCarpeta $carpeta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->empresa = Empresa::factory()->activa()->create();
        $this->usuario = $this->companyUser($this->empresa, [
            'empleados.view',
            'empleados_documentos_catalogo.view',
            'empleados_documentos_catalogo.create',
            'empleados_documentos_catalogo.update',
            'empleados_documentos_catalogo.delete',
        ]);
        $this->carpeta = EmpleadoCarpeta::factory()->create([
            'empresa_id' => $this->empresa->id,
            'creado_por_id' => $this->usuario->id,
            'nombre' => 'Personal',
        ]);
        $this->empleado = Empleado::factory()->create(['empresa_id' => $this->empresa->id]);
        $this->withEmpresaContext($this->empresa);
    }

    public function test_user_sees_documents_ordered_inside_only_accessible_folders(): void
    {
        $sharedUser = $this->companyUser($this->empresa, ['empleados_documentos_catalogo.view']);
        $sharedFolder = EmpleadoCarpeta::factory()->create([
            'empresa_id' => $this->empresa->id,
            'creado_por_id' => $sharedUser->id,
            'nombre' => 'Compartida',
        ]);
        $sharedFolder->usuariosConAcceso()->attach($this->usuario->id, ['empresa_id' => $this->empresa->id]);
        $first = $this->documento($this->carpeta, 'Acuerdo');
        $second = $this->documento($this->carpeta, 'Contrato');
        $this->documento($sharedFolder, 'Reglamento');

        $foreignCompany = Empresa::factory()->activa()->create();
        $foreignFolder = EmpleadoCarpeta::factory()->create([
            'empresa_id' => $foreignCompany->id,
            'creado_por_id' => $sharedUser->id,
        ]);
        $this->documento($foreignFolder, 'Privado');

        $this->actingAs($this->usuario)
            ->get(route('empresas.empleados.documentos-catalogo.index', ['carpeta_id' => $this->carpeta->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('empleados/documentos-catalogo/index')
                ->has('documentos.data', 2)
                ->where('documentos.data.0.id', $first->id)
                ->where('documentos.data.1.id', $second->id)
                ->where('carpetaSeleccionada.id', $this->carpeta->id)
                ->missing('documentos.data.0.contenido_html')
                ->has('carpetas', 2));

        $this->actingAs($this->usuario)
            ->get(route('empresas.empleados.documentos-catalogo.index', ['carpeta_id' => $foreignFolder->id]))
            ->assertNotFound();
    }

    public function test_catalog_search_archive_filter_and_pagination_preserve_folder_context(): void
    {
        $documents = [];

        for ($index = 1; $index <= 7; $index++) {
            $documents[] = $this->documento($this->carpeta, sprintf('Formato %02d', $index));
        }

        $this->documento($this->carpeta, 'Circular interna');

        $response = $this->actingAs($this->usuario)
            ->get(route('empresas.empleados.documentos-catalogo.index', [
                'carpeta_id' => $this->carpeta->id,
                'search' => 'Formato',
                'per_page' => 2,
            ]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'Formato')
                ->where('filters.perPage', 2)
                ->where('documentos.current_page', 1)
                ->has('documentos.data', 2)
                ->where('documentos.data.0.id', $documents[0]->id)
                ->where('documentos.next_page_url', fn (?string $url): bool => $url !== null
                    && str_contains($url, 'carpeta_id='.$this->carpeta->id)
                    && str_contains($url, 'search=Formato')
                    && str_contains($url, 'per_page=2')
                    && str_contains($url, 'page=2')));

        $this->actingAs($this->usuario)
            ->get(route('empresas.empleados.documentos-catalogo.index', [
                'carpeta_id' => $this->carpeta->id,
                'search' => 'Formato',
                'per_page' => 2,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('documentos.current_page', 2)
                ->has('documentos.data', 2)
                ->where('documentos.data.0.id', $documents[2]->id)
                ->where('filters.carpetaId', $this->carpeta->id));

        $documents[0]->delete();

        $this->actingAs($this->usuario)
            ->get(route('empresas.empleados.documentos-catalogo.index', [
                'carpeta_id' => $this->carpeta->id,
                'search' => 'Formato',
                'archivados' => true,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.archivados', true)
                ->has('documentos.data', 1)
                ->where('documentos.data.0.id', $documents[0]->id));
    }

    public function test_document_is_saved_with_folder_and_unsafe_html_is_removed(): void
    {
        $this->actingAs($this->usuario)
            ->post(route('empresas.empleados.documentos-catalogo.store'), [
                'nombre' => '  Contrato   laboral ',
                'empleado_carpeta_id' => $this->carpeta->id,
                'contenido_html' => '<p>Hola {{nombre_empleado}}</p><script>alert(1)</script><img src="file:///etc/passwd" onerror="alert(1)">',
            ])
            ->assertSessionHasNoErrors();

        $documento = EmpleadoDocumentoCatalogo::query()->where('nombre', 'Contrato laboral')->firstOrFail();

        $this->assertSame($this->empresa->id, $documento->empresa_id);
        $this->assertSame($this->carpeta->id, $documento->empleado_carpeta_id);
        $this->assertStringContainsString('{{nombre_empleado}}', $documento->contenido_html);
        $this->assertStringNotContainsString('<script', $documento->contenido_html);
        $this->assertStringNotContainsString('file://', $documento->contenido_html);
        $this->assertStringNotContainsString('onerror', $documento->contenido_html);
    }

    public function test_unknown_variables_and_inaccessible_folders_are_rejected(): void
    {
        $other = $this->companyUser($this->empresa, ['empleados_documentos_catalogo.view']);
        $privateFolder = EmpleadoCarpeta::factory()->create([
            'empresa_id' => $this->empresa->id,
            'creado_por_id' => $other->id,
        ]);

        $this->actingAs($this->usuario)
            ->post(route('empresas.empleados.documentos-catalogo.store'), [
                'nombre' => 'Documento inválido',
                'empleado_carpeta_id' => $privateFolder->id,
                'contenido_html' => '<p>{{comando_php}}</p>',
            ])
            ->assertSessionHasErrors(['empleado_carpeta_id', 'contenido_html']);

        $this->assertDatabaseMissing('empleados_documentos_catalogo', ['nombre' => 'Documento inválido']);
    }

    public function test_document_can_move_and_be_archived_and_restored(): void
    {
        $secondFolder = EmpleadoCarpeta::factory()->create([
            'empresa_id' => $this->empresa->id,
            'creado_por_id' => $this->usuario->id,
            'nombre' => 'Contratos',
        ]);
        $documento = $this->documento($this->carpeta, 'Formato inicial');

        $this->actingAs($this->usuario)
            ->put(route('empresas.empleados.documentos-catalogo.update', $documento), [
                'nombre' => 'Formato actualizado',
                'empleado_carpeta_id' => $secondFolder->id,
                'contenido_html' => '<p>{{nombre_empleado}}</p>',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('empleados_documentos_catalogo', [
            'id' => $documento->id,
            'nombre' => 'Formato actualizado',
            'empleado_carpeta_id' => $secondFolder->id,
        ]);

        $this->actingAs($this->usuario)
            ->delete(route('empresas.empleados.documentos-catalogo.destroy', $documento))
            ->assertSessionHasNoErrors();
        $this->assertSoftDeleted($documento);

        $this->actingAs($this->usuario)
            ->patch(route('empresas.empleados.documentos-catalogo.restore', $documento))
            ->assertSessionHasNoErrors();
        $this->assertNotSoftDeleted($documento);
    }

    public function test_editor_fetches_html_only_for_authorized_document(): void
    {
        $documento = $this->documento($this->carpeta, 'Carta laboral', '<p>{{nombre_empleado}}</p>');
        $viewer = $this->companyUser($this->empresa, ['empleados_documentos_catalogo.view']);

        $this->actingAs($this->usuario)
            ->get(route('empresas.empleados.documentos-catalogo.show', $documento))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('documento.id', $documento->id)
            ->assertJsonPath('documento.contenido_html', '<p>{{nombre_empleado}}</p>');

        $this->actingAs($viewer)
            ->get(route('empresas.empleados.documentos-catalogo.show', $documento))
            ->assertForbidden();

        $otherCompany = Empresa::factory()->activa()->create();
        $otherFolder = EmpleadoCarpeta::factory()->create(['empresa_id' => $otherCompany->id]);
        $otherDocument = $this->documento($otherFolder, 'Documento de otra empresa');

        $this->actingAs($this->usuario)
            ->get(route('empresas.empleados.documentos-catalogo.show', $otherDocument))
            ->assertNotFound();
    }

    public function test_inline_document_images_are_compressed_before_persistence(): void
    {
        config(['media.images.max_dimension' => 2400]);

        $this->actingAs($this->usuario)
            ->post(route('empresas.empleados.documentos-catalogo.store'), [
                'nombre' => 'Formato con imagen',
                'empleado_carpeta_id' => $this->carpeta->id,
                'contenido_html' => '<p>Encabezado</p><img src="'.$this->pngDataUri(3000, 1500).'">',
            ])
            ->assertSessionHasNoErrors();

        $documento = EmpleadoDocumentoCatalogo::query()->where('nombre', 'Formato con imagen')->firstOrFail();
        preg_match('/src="data:image\/png;base64,([^"]+)"/', $documento->contenido_html, $matches);
        $this->assertArrayHasKey(1, $matches);
        $imageContents = base64_decode($matches[1], true);
        $this->assertIsString($imageContents);
        $imageInfo = getimagesizefromstring($imageContents);
        $this->assertIsArray($imageInfo);
        $this->assertSame(2400, $imageInfo[0]);
        $this->assertSame(1200, $imageInfo[1]);
    }

    public function test_inline_document_images_over_pixel_limit_are_rejected(): void
    {
        config(['media.images.max_source_pixels' => 3]);

        $this->actingAs($this->usuario)
            ->post(route('empresas.empleados.documentos-catalogo.store'), [
                'nombre' => 'Formato con imagen grande',
                'empleado_carpeta_id' => $this->carpeta->id,
                'contenido_html' => '<img src="'.$this->pngDataUri(2, 2).'">',
            ])
            ->assertSessionHasErrors('contenido_html');

        $this->assertDatabaseMissing('empleados_documentos_catalogo', [
            'nombre' => 'Formato con imagen grande',
        ]);
    }

    public function test_user_without_catalog_permission_cannot_list_documents(): void
    {
        $user = $this->companyUser($this->empresa, ['empleados.view']);

        $this->actingAs($user)
            ->get(route('empresas.empleados.documentos-catalogo.index'))
            ->assertForbidden();
    }

    public function test_create_update_delete_and_employee_print_require_their_permissions(): void
    {
        $documento = $this->documento($this->carpeta, 'Carta laboral');
        $viewer = $this->companyUser($this->empresa, [
            'empleados.view',
            'empleados_documentos_catalogo.view',
        ]);

        $this->actingAs($viewer)
            ->post(route('empresas.empleados.documentos-catalogo.store'), [
                'nombre' => 'Nuevo documento',
                'empleado_carpeta_id' => $this->carpeta->id,
                'contenido_html' => '<p>{{nombre_empleado}}</p>',
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->put(route('empresas.empleados.documentos-catalogo.update', $documento), [
                'nombre' => 'Carta actualizada',
                'empleado_carpeta_id' => $this->carpeta->id,
                'contenido_html' => '<p>{{nombre_empleado}}</p>',
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->delete(route('empresas.empleados.documentos-catalogo.destroy', $documento))
            ->assertForbidden();

        $employeeViewer = $this->companyUser($this->empresa, ['empleados.view']);

        $this->actingAs($employeeViewer)
            ->get(route('empresas.empleados.documentos-catalogo.seleccionar', $this->empleado))
            ->assertForbidden();
    }

    public function test_print_selection_shows_only_documents_in_accessible_folders(): void
    {
        $documento = $this->documento($this->carpeta, 'Carta laboral');

        $this->actingAs($this->usuario)
            ->get(route('empresas.empleados.documentos-catalogo.seleccionar', [
                'empleado' => $this->empleado,
                'carpeta_id' => $this->carpeta->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('empleados/documentos-catalogo/imprimir')
                ->where('empleado.id', $this->empleado->id)
                ->has('carpetas', 1)
                ->where('carpetas.0.documentos_count', 1)
                ->where('carpetaSeleccionada.id', $this->carpeta->id)
                ->has('documentos.data', 1)
                ->where('documentos.data.0.id', $documento->id)
                ->missing('carpetas.0.documentos'));
    }

    public function test_print_selection_paginates_documents_with_folder_context(): void
    {
        for ($index = 1; $index <= 53; $index++) {
            $this->documento($this->carpeta, sprintf('Documento %02d', $index));
        }

        $this->actingAs($this->usuario)
            ->get(route('empresas.empleados.documentos-catalogo.seleccionar', [
                'empleado' => $this->empleado,
                'carpeta_id' => $this->carpeta->id,
                'per_page' => 2,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('documentos.current_page', 2)
                ->where('documentos.total', 53)
                ->has('documentos.data', 2)
                ->where('documentos.data.0.nombre', 'Documento 03')
                ->where('documentos.next_page_url', fn (?string $url): bool => $url !== null
                    && str_contains($url, 'carpeta_id='.$this->carpeta->id)
                    && str_contains($url, 'per_page=2')
                    && str_contains($url, 'page=3')));
    }

    public function test_single_print_download_returns_rendered_pdf_for_employee(): void
    {
        $documento = $this->documento($this->carpeta, 'Carta laboral', '<p>Empleado: {{nombre_empleado}}</p>');
        $this->empleado->forceFill(['nombre' => 'Sofía <Rojas>'])->save();

        $renderedHtml = app(RenderEmpleadoDocumentoPdf::class)->renderHtml($documento, $this->empleado);

        $this->assertStringContainsString('Sofía &lt;Rojas&gt;', $renderedHtml);
        $this->assertStringNotContainsString('{{nombre_empleado}}', $renderedHtml);

        $response = $this->actingAs($this->usuario)
            ->get(route('empresas.empleados.documentos-catalogo.imprimir', [
                'empleado' => $this->empleado->id,
                'documento_ids' => [$documento->id],
            ]));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
    }

    public function test_catalog_download_returns_template_as_pdf(): void
    {
        $documento = $this->documento($this->carpeta, 'Carta laboral');

        $response = $this->actingAs($this->usuario)
            ->get(route('empresas.empleados.documentos-catalogo.download', $documento));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
    }

    public function test_multiple_print_download_returns_zip_with_one_pdf_per_template(): void
    {
        $first = $this->documento($this->carpeta, 'Carta laboral');
        $second = $this->documento($this->carpeta, 'Aviso privacidad');

        $response = $this->actingAs($this->usuario)
            ->get(route('empresas.empleados.documentos-catalogo.imprimir', [
                'empleado' => $this->empleado->id,
                'documento_ids' => [$first->id, $second->id],
            ]));

        $response->assertOk()->assertHeader('Content-Type', 'application/zip');
        $archivePath = $response->baseResponse->getFile()->getPathname();
        $archive = new ZipArchive;

        $this->assertSame(true, $archive->open($archivePath));
        $this->assertSame(2, $archive->numFiles);

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $this->assertStringEndsWith('.pdf', $archive->getNameIndex($index));
            $this->assertStringStartsWith('%PDF-', $archive->getFromIndex($index));
        }

        $archive->close();
        unlink($archivePath);
    }

    public function test_print_rejects_document_outside_user_folder_access(): void
    {
        $other = $this->companyUser($this->empresa, ['empleados_documentos_catalogo.view']);
        $privateFolder = EmpleadoCarpeta::factory()->create([
            'empresa_id' => $this->empresa->id,
            'creado_por_id' => $other->id,
        ]);
        $privateDocument = $this->documento($privateFolder, 'Privado');

        $this->actingAs($this->usuario)
            ->get(route('empresas.empleados.documentos-catalogo.imprimir', [
                'empleado' => $this->empleado->id,
                'documento_ids' => [$privateDocument->id],
            ]))
            ->assertSessionHasErrors('documento_ids');
    }

    public function test_employee_module_entitlement_blocks_catalog_routes(): void
    {
        $empleadosModule = Modulo::query()->where('clave', 'empleados')->firstOrFail();
        $this->empresa->modulos()->updateExistingPivot($empleadosModule->id, ['habilitado' => false]);

        $this->actingAs($this->usuario)
            ->get(route('empresas.empleados.documentos-catalogo.index'))
            ->assertForbidden();
    }

    private function documento(EmpleadoCarpeta $carpeta, string $nombre, string $html = '<p>{{nombre_empleado}}</p>'): EmpleadoDocumentoCatalogo
    {
        return EmpleadoDocumentoCatalogo::factory()->create([
            'empresa_id' => $carpeta->empresa_id,
            'empleado_carpeta_id' => $carpeta->id,
            'nombre' => $nombre,
            'contenido_html' => $html,
        ]);
    }

    private function pngDataUri(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);

        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode((string) $contents);
    }

    /** @param list<string> $permissions */
    private function companyUser(Empresa $empresa, array $permissions): User
    {
        $user = User::factory()->create();
        MembresiaEmpresa::factory()->for($empresa)->for($user)->create();

        if ($permissions !== []) {
            setPermissionsTeamId($empresa->id);
            $user->givePermissionTo($permissions);
        }

        return $user;
    }
}
