<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
   public function share(Request $request): array
    {
        // The installer can run while the configured database is unavailable.
        // In that state Laravel uses a temporary SQLite connection with no users
        // table, so a stale remember-me cookie must not trigger a user lookup.
        $isSetupRequest = $request->is('setup', 'setup/*');

        return array_merge(
            parent::share($request),
            [

                'auth' => [

                    'user' => fn () => $isSetupRequest ? null : $request->user(),

                    'permissions' => fn () =>
                        ! $isSetupRequest && $request->user()
                            ? $request->user()
                                ->getAllPermissions()
                                ->pluck('name')
                                ->values()
                            : [],

                    'roles' => fn () =>
                        ! $isSetupRequest && $request->user()
                            ? $request->user()
                                ->getRoleNames()
                                ->values()
                            : [],

                ],

                // Cảnh báo ổ đĩa: chỉ gửi cho người có quyền quản lý sao lưu và chỉ khi có vấn đề.
                'diskHealth' => fn () => $this->diskHealthFor($request, $isSetupRequest),

                // Cảnh báo lịch chạy nền (sao lưu tự động, nhắc việc) đã ngừng: chỉ cho người quản lý sao lưu.
                'schedulerHealth' => fn () => $this->schedulerHealthFor($request, $isSetupRequest),

                'flash' => [
                    'success' => fn () => session()->pull('success'),
                    'error' => fn () => session()->pull('error'),
                    'newSupplier' => fn () => session()->pull('newSupplier'),
                    'createdRepairId' => fn () => session()->pull('createdRepairId'),
                    'createdProductId' => fn () => session()->pull('createdProductId'),
                ],

            ]
        );
    }

    private function diskHealthFor(Request $request, bool $isSetupRequest): ?array
    {
        if ($isSetupRequest || ! $request->user() || ! $request->user()->can('backups.manage')) {
            return null;
        }

        try {
            $report = \App\Support\DiskHealth::report();
        } catch (\Throwable) {
            return null;
        }

        return $report['level'] === 'ok' ? null : $report;
    }

    private function schedulerHealthFor(Request $request, bool $isSetupRequest): ?array
    {
        if ($isSetupRequest || ! $request->user() || ! $request->user()->can('backups.manage')) {
            return null;
        }

        return \App\Support\SchedulerHealth::problem();
    }
}
