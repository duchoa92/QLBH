<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\CustomerVisitController;
use App\Http\Controllers\Api\CustomerFaceController;
use App\Models\Customer;
use App\Models\CustomerFaceSample;
use App\Models\CustomerVisit;
use App\Models\Setting;
use App\Services\CustomerVisitRetention;
use App\Services\FaceImageStorage;
use App\Support\FaceMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerVisitRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Setting keeps a process-wide table/cache flag. Clear it before the
        // next feature class receives its own freshly migrated SQLite schema.
        $this->resetSettingModelState();

        parent::tearDown();
    }

    public function test_retention_deletes_expired_visit_rows_and_their_private_images(): void
    {
        $this->resetSettingModelState();
        Storage::fake('local');
        Setting::set('face_visit_retention_days', 7);

        $expiredPath = 'customer-visits/2026/11/expired.jpg';
        $currentPath = 'customer-visits/2026/11/current.jpg';
        Storage::disk('local')->put($expiredPath, 'expired');
        Storage::disk('local')->put($currentPath, 'current');

        $expired = CustomerVisit::query()->create([
            'recognized' => false,
            'image_path' => $expiredPath,
            'first_seen_at' => now()->subDays(9),
            'last_seen_at' => now()->subDays(8),
        ]);
        $current = CustomerVisit::query()->create([
            'recognized' => false,
            'image_path' => $currentPath,
            'first_seen_at' => now()->subDays(2),
            'last_seen_at' => now()->subDay(),
        ]);

        $deleted = app(CustomerVisitRetention::class)->prune();

        $this->assertSame(1, $deleted);
        $this->assertModelMissing($expired);
        $this->assertModelExists($current);
        Storage::disk('local')->assertMissing($expiredPath);
        Storage::disk('local')->assertExists($currentPath);
    }

    public function test_camera_visit_persists_its_snapshot_on_the_private_disk(): void
    {
        $this->resetSettingModelState();
        Storage::fake('local');
        Setting::set('face_recognition_enabled', true);
        Setting::set('face_visit_tracking_enabled', true);

        $response = app(CustomerVisitController::class)->record(Request::create('/api/camera-intelligence/visits', 'POST', [
            'camera_id' => 'door-1',
            'engine' => FaceMatcher::SFACE,
            'descriptor' => array_fill(0, FaceMatcher::SFACE_SIZE, 0.01),
            'thumb' => $this->jpegDataUrl(),
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $visit = CustomerVisit::query()->sole();
        $this->assertNull($visit->thumb);
        $this->assertNotNull($visit->image_path);
        Storage::disk('local')->assertExists($visit->image_path);
    }

    public function test_dashboard_returns_json_for_silent_background_refreshes(): void
    {
        $this->resetSettingModelState();
        $visit = CustomerVisit::query()->create([
            'recognized' => false,
            'first_seen_at' => now()->subMinute(),
            'last_seen_at' => now(),
        ]);

        $response = app(CustomerVisitController::class)->dashboard(Request::create(
            '/api/customer-visits/dashboard?range=today&per_page=10',
            'GET',
        ));

        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, $payload['summary']['total']);
        $this->assertSame($visit->id, $payload['visits']['data'][0]['id']);
    }

    public function test_enrolling_a_face_keeps_accepted_sample_images_on_the_private_disk(): void
    {
        $this->resetSettingModelState();
        Storage::fake('local');
        Http::fake([
            '*/enroll' => Http::response([
                'descriptors' => [array_fill(0, FaceMatcher::SFACE_SIZE, 0.01)],
            ]),
        ]);
        Setting::set('face_recognition_enabled', true);
        $customer = Customer::query()->create([
            'code' => 'KH-TEST-FACE',
            'full_name' => 'Khách thử ảnh mẫu',
            'customer_type' => 'retail',
            'is_active' => true,
        ]);

        $response = app(CustomerFaceController::class)->store(
            Request::create("/api/customers/{$customer->id}/face", 'POST', [
                'consent' => true,
                'images' => [$this->jpegDataUrl()],
                'sources' => ['camera'],
            ]),
            $customer,
            app(FaceImageStorage::class),
        );

        $payload = $response->getData(true);
        $sample = CustomerFaceSample::query()->sole();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($sample->id, $payload['sample_images'][0]['id']);
        $this->assertSame('camera', $sample->source);
        Storage::disk('local')->assertExists($sample->image_path);
    }

    private function jpegDataUrl(): string
    {
        $image = imagecreatetruecolor(48, 48);
        imagefill($image, 0, 0, imagecolorallocate($image, 25, 100, 180));
        ob_start();
        imagejpeg($image, null, 85);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return 'data:image/jpeg;base64,'.base64_encode($bytes);
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
