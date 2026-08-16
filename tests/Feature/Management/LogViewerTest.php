<?php

namespace Tests\Feature\Management;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Opcodes\LogViewer\Facades\LogViewer;
use Opcodes\LogViewer\LogFile;
use Tests\TestCase;

class LogViewerTest extends TestCase
{
    use RefreshDatabase;

    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->logPath = storage_path('logs/log-viewer-test.log');

        File::ensureDirectoryExists(dirname($this->logPath));
        File::put(
            $this->logPath,
            '[2026-08-16 12:00:00] testing.INFO: Log Viewer test entry'.PHP_EOL,
        );
        LogViewer::clearFileCache();
    }

    protected function tearDown(): void
    {
        File::delete($this->logPath);

        parent::tearDown();
    }

    public function test_user_with_view_permission_can_open_log_viewer(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('logs.view');

        $this->actingAs($user)
            ->get(route('log-viewer.index'))
            ->assertOk();

        $file = $this->test_log_file();

        $this->actingAs($user)
            ->get(route('log-viewer.files'))
            ->assertOk()
            ->assertJsonFragment([
                'name' => $file->name,
                'can_download' => true,
                'can_delete' => false,
            ]);
    }

    public function test_user_without_view_permission_cannot_open_log_viewer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('log-viewer.index'))
            ->assertForbidden();
    }

    public function test_guest_cannot_open_log_viewer(): void
    {
        $this->get(route('log-viewer.index'))
            ->assertForbidden();
    }

    public function test_logs_menu_route_supports_full_page_navigation(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('logs.view');

        $this->actingAs($user)
            ->get(route('logs.index'))
            ->assertRedirect(route('log-viewer.index'));

        $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => Inertia::getVersion(),
            ])
            ->get(route('logs.index'))
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('log-viewer.index'));
    }

    public function test_user_with_delete_permission_can_delete_log_file(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['logs.view', 'logs.delete']);
        $file = $this->test_log_file();

        $this->actingAs($user)
            ->delete(route('log-viewer.files.delete', $file->identifier))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertFileDoesNotExist($this->logPath);
    }

    public function test_viewer_without_delete_permission_cannot_delete_log_file(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('logs.view');
        $file = $this->test_log_file();

        $this->actingAs($user)
            ->delete(route('log-viewer.files.delete', $file->identifier))
            ->assertForbidden();

        $this->assertFileExists($this->logPath);
    }

    private function test_log_file(): LogFile
    {
        return LogViewer::getFiles()->firstOrFail(
            fn (LogFile $file): bool => $file->name === basename($this->logPath),
        );
    }
}
