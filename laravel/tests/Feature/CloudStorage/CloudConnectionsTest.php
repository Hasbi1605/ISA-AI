<?php

namespace Tests\Feature\CloudStorage;

use App\Livewire\CloudStorage\CloudConnections;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CloudConnectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cloud_connections_page_reports_drive_status(): void
    {
        config([
            'services.google_drive.service_account_json' => json_encode([
                'type' => 'service_account',
                'client_email' => 'ista-ai@example.test',
            ]),
            'services.google_drive.root_folder_id' => 'root-folder-id',
            'services.google_drive.default_upload_folder_name' => 'ISTA AI',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('cloud-storage.index'))
            ->assertOk()
            ->assertSee('Drive kantor aktif', false)
            ->assertSee('root-folder-id', false)
            ->assertSee('Folder Upload Default', false)
            ->assertSee('Buka Browser Drive', false);

        Livewire::actingAs($user)
            ->test(CloudConnections::class)
            ->assertSee('Drive kantor aktif', false);
    }
}
