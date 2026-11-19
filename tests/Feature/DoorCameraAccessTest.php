<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\DoorCameraAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class DoorCameraAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_multi_camera_save_keeps_the_existing_door_camera_password(): void
    {
        $this->resetSettingModelState();
        Setting::set('face_cctv_snapshot_url', 'rtsp://192.168.1.50:554/main');
        Setting::set('face_cctv_view_rtsp_url', 'rtsp://192.168.1.50:554/sub');
        Setting::set('face_cctv_username', 'legacy-user');
        Setting::set('face_cctv_password', Crypt::encryptString('legacy-secret'));

        DoorCameraAccess::storeCameras([
            [
                'id' => 'door-1',
                'name' => 'Cửa chính',
                'enabled' => true,
                'snapshot_url' => 'rtsp://192.168.1.50:554/main',
                'view_rtsp_url' => 'rtsp://192.168.1.50:554/sub',
                'auth' => 'basic',
                'username' => 'legacy-user',
                'password' => '',
            ],
            [
                'id' => 'side-door',
                'name' => 'Cửa phụ',
                'enabled' => true,
                'snapshot_url' => 'rtsp://operator:new-secret@192.168.1.51:554/main',
                'view_rtsp_url' => 'rtsp://operator:new-secret@192.168.1.51:554/sub',
                'auth' => 'basic',
            ],
        ]);

        $cameras = collect(DoorCameraAccess::cameras())->keyBy('id');

        $this->assertCount(2, $cameras);
        $this->assertSame('legacy-secret', $cameras['door-1']['password']);
        $this->assertSame('legacy-user', $cameras['door-1']['username']);
        $this->assertSame('new-secret', $cameras['side-door']['password']);
        $this->assertSame('operator', $cameras['side-door']['username']);
        $this->assertCount(2, DoorCameraAccess::configuredCameras());
    }

    public function test_public_camera_entries_do_not_disclose_passwords(): void
    {
        $this->resetSettingModelState();
        DoorCameraAccess::storeCameras([[
            'id' => 'door-1',
            'name' => 'Camera cửa',
            'enabled' => true,
            'snapshot_url' => 'rtsp://admin:secret@192.168.1.50:554/main',
            'view_rtsp_url' => 'rtsp://admin:secret@192.168.1.50:554/sub',
            'auth' => 'basic',
        ]]);

        $public = DoorCameraAccess::publicCameras()[0];

        $this->assertArrayNotHasKey('password', $public);
        $this->assertTrue($public['has_password']);
        $this->assertSame('rtsp://192.168.1.50:554/main', $public['snapshot_url']);
    }

    private function resetSettingModelState(): void
    {
        $reflection = new \ReflectionClass(Setting::class);
        foreach (['settingsTableAvailable', 'settingsCache'] as $property) {
            $item = $reflection->getProperty($property);
            $item->setValue(null);
        }
    }
}
