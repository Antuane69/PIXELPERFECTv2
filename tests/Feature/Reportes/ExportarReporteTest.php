<?php

namespace Tests\Feature\Reportes;

use App\Models\Puesto;
use App\Models\User;
use App\Services\Reportes\ExportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Mockery\MockInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class ExportarReporteTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->administrator = User::factory()->create();
        $this->administrator->assignRole('Administrador');
        Date::setTestNow('2026-08-12 10:00:00');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    public function test_guest_cannot_export_reports(): void
    {
        $this->post(route('reportes.exportar', 'puestos'), ['formato' => 'xlsx'])
            ->assertRedirect(route('login'));
    }

    public function test_user_without_listing_permission_cannot_export_report(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('reportes.exportar', 'puestos'), ['formato' => 'xlsx'])
            ->assertForbidden();
    }

    public function test_export_request_validates_format_and_registered_report(): void
    {
        $this->actingAs($this->administrator)
            ->post(route('reportes.exportar', 'puestos'), ['formato' => 'csv'])
            ->assertSessionHasErrors('formato');

        $this->actingAs($this->administrator)
            ->post(route('reportes.exportar', 'desconocido'), ['formato' => 'xlsx'])
            ->assertNotFound();
    }

    public function test_administrator_can_request_every_registered_report(): void
    {
        $this->mock(ExportService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('excelFromQuery')
                ->times(5)
                ->andReturnUsing(function (): BinaryFileResponse {
                    $path = tempnam(sys_get_temp_dir(), 'reporte-prueba-');
                    file_put_contents($path, 'xlsx');

                    return response()->download($path, 'reporte.xlsx')->deleteFileAfterSend(true);
                });
        });

        foreach ([
            'empleados',
            'puestos',
            'roles',
            'tipos-documento-empleados',
            'usuarios',
        ] as $reporte) {
            $this->actingAs($this->administrator)
                ->post(route('reportes.exportar', $reporte), ['formato' => 'xlsx'])
                ->assertOk()
                ->assertDownload('reporte.xlsx');
        }
    }

    public function test_excel_export_contains_every_filtered_position_not_only_one_page(): void
    {
        Puesto::factory()->create(['nombre' => 'Needle Activo', 'activo' => true]);
        Puesto::factory()->inactive()->create(['nombre' => 'Needle Inactivo']);
        Puesto::factory()->create(['nombre' => 'Otro puesto', 'activo' => true]);

        $response = $this->actingAs($this->administrator)
            ->post(route('reportes.exportar', 'puestos'), [
                'formato' => 'xlsx',
                'filtros' => [
                    'search' => 'Needle',
                    'activo' => true,
                    'archivados' => false,
                ],
            ])
            ->assertOk()
            ->assertDownload('puestos_20260812_100000.xlsx');

        $spreadsheet = IOFactory::load($response->baseResponse->getFile()->getPathname());
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame('Nombre', (string) $sheet->getCell('B5')->getValue());
        $this->assertSame('Needle Activo', (string) $sheet->getCell('B6')->getValue());
        $this->assertSame('', (string) $sheet->getCell('B7')->getValue());
        $this->assertSame('FF935AAC', $sheet->getStyle('B5')->getFill()->getStartColor()->getARGB());
        $this->assertSame('FFFFFFFF', $sheet->getStyle('B5')->getFont()->getColor()->getARGB());
        $this->assertSame('FFFCF9FE', $sheet->getStyle('B6')->getFill()->getStartColor()->getARGB());
        $this->assertFalse($sheet->getShowGridlines());

        $spreadsheet->disconnectWorksheets();
    }

    public function test_pdf_export_generates_a_download(): void
    {
        Puesto::factory()->create(['nombre' => 'Puesto PDF']);

        $response = $this->actingAs($this->administrator)
            ->post(route('reportes.exportar', 'puestos'), ['formato' => 'pdf'])
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('puestos_20260812_100000.pdf');

        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }
}
