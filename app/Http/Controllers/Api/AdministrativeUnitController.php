<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AdministrativeUnitController extends Controller
{
    private const API = 'https://www.tinhthanhpho.com/api/v1';

    public function provinces(): JsonResponse
    {
        try {
            $provinces = Cache::remember('administrative-units.provinces.v2026', now()->addDays(7), function (): array {
                $response = Http::acceptJson()->timeout(10)->get(self::API . '/new-provinces', ['limit' => 100]);
                $response->throw();
                return collect($response->json('data', []))
                    ->map(fn (array $item) => [
                        'code' => (string) $item['code'],
                        'name' => trim(($item['type'] ?? '') . ' ' . ($item['name'] ?? '')),
                    ])
                    ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all();
            });

            return response()->json(['data' => $provinces]);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['message' => 'Không tải được danh sách tỉnh/thành. Bạn có thể nhập thủ công.'], 503);
        }
    }

    public function wards(string $provinceCode): JsonResponse
    {
        abort_unless(preg_match('/^\d{2}$/', $provinceCode) === 1, 404);

        try {
            $wards = Cache::remember('administrative-units.wards.v2026.' . $provinceCode, now()->addDays(7), function () use ($provinceCode): array {
                $items = [];
                $page = 1;
                $total = null;
                do {
                    $response = Http::acceptJson()->timeout(10)->get(self::API . '/new-provinces/' . $provinceCode . '/wards', [
                        'limit' => 500,
                        'page' => $page,
                    ]);
                    $response->throw();
                    $payload = $response->json();
                    $items = [...$items, ...($payload['data'] ?? [])];
                    $total = (int) ($payload['metadata']['total'] ?? count($items));
                    $page++;
                } while (count($items) < $total && $page <= 20);

                return collect($items)
                    ->map(fn (array $item) => [
                        'code' => (string) $item['code'],
                        'name' => trim(($item['type'] ?? '') . ' ' . ($item['name'] ?? '')),
                    ])
                    ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all();
            });

            return response()->json(['data' => $wards]);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['message' => 'Không tải được danh sách phường/xã. Bạn có thể nhập thủ công.'], 503);
        }
    }
}
