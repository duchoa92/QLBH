<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->dispatchDueReminders();
        $user = $request->user();
        $notifications = $user->notifications()->latest()->cursor()
            ->filter(fn ($notification): bool => $this->userCanViewNotification($user, $notification->data ?? []))
            ->take(30)
            ->map(fn ($notification): array => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'Thông báo hệ thống',
                'message' => $notification->data['message'] ?? '',
                'type' => $notification->data['type'] ?? 'info',
                'category' => $this->notificationCategory($notification->data),
                'url' => $this->notificationPath($notification->data),
                'read' => $notification->read_at !== null,
                'time' => $notification->created_at?->diffForHumans(),
            ])->values();
        $visibleUnreadCount = $user->unreadNotifications()->cursor()
            ->filter(fn ($notification): bool => $this->userCanViewNotification($user, $notification->data ?? []))
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $visibleUnreadCount,
        ]);
    }

    private function userCanViewNotification($user, array $notification): bool
    {
        $permission = $notification['required_permission'] ?? null;
        if (! is_string($permission) || $permission === '') {
            $url = $this->internalPath($notification['url'] ?? null);
            $path = $url ? parse_url($url, PHP_URL_PATH) : null;
            $permission = match (true) {
                is_string($path) && preg_match('#^/records/sale/\d+$#', $path) === 1,
                is_string($path) && str_starts_with($path, '/sales') => 'sales.view',
                is_string($path) && preg_match('#^/records/repair/\d+$#', $path) === 1,
                is_string($path) && str_starts_with($path, '/repairs') => 'repairs.view',
                is_string($path) && str_starts_with($path, '/pos') => 'pos.access',
                is_string($path) && str_starts_with($path, '/expenses') => 'expenses.view',
                is_string($path) && str_starts_with($path, '/debts/customers') => 'customer_debts.view',
                is_string($path) && str_starts_with($path, '/debts/suppliers') => 'supplier_debts.view',
                ($notification['type'] ?? null) === 'sale' => 'sales.view',
                ($notification['type'] ?? null) === 'repair' => 'repairs.view',
                ($notification['reminder_kind'] ?? null) === 'recurring_expense' => 'expenses.view',
                ($notification['reminder_kind'] ?? null) === 'debt' && ($notification['party_type'] ?? null) === 'customer' => 'customer_debts.view',
                ($notification['reminder_kind'] ?? null) === 'debt' && ($notification['party_type'] ?? null) === 'supplier' => 'supplier_debts.view',
                default => null,
            };
        }

        return $permission === null || $user->can($permission);
    }

    private function dispatchDueReminders(): void
    {
        $today = now()->toDateString();
        $dailyKey = 'system_reminder_check:expenses:' . $today;
        if (Cache::add($dailyKey, true, now()->addHours(36))) {
            try {
                Artisan::call('system:send-reminders');
            } catch (\Throwable) {
                Cache::forget($dailyKey);
            }
        }

        $weekKey = 'system_reminder_check:debts:' . now()->startOfWeek(Carbon::MONDAY)->toDateString();
        if (Cache::add($weekKey, true, now()->addDays(8))) {
            try {
                Artisan::call('system:send-debt-reminders');
            } catch (\Throwable) {
                Cache::forget($weekKey);
            }
        }
    }

    private function internalPath(mixed $url): ?string
    {
        if (!is_string($url) || $url === '') return null;
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || !str_starts_with($path, '/') || str_starts_with($path, '//')) return null;
        $query = parse_url($url, PHP_URL_QUERY);
        return $path.($query ? '?'.$query : '');
    }

    private function notificationPath(array $data): ?string
    {
        $url = $this->internalPath($data['url'] ?? null);
        if (! $url) return null;

        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#^/records/sale/(\d+)$#', $path, $matches)) {
            return route('sales.index', ['open_sale' => (int) $matches[1]], false);
        }
        if (is_string($path) && preg_match('#^/records/repair/(\d+)$#', $path, $matches)) {
            return route('repairs.index', ['open_repair' => (int) $matches[1]], false);
        }

        return $url;
    }

    private function notificationCategory(array $data): string
    {
        $path = parse_url($this->internalPath($data['url'] ?? null) ?? '', PHP_URL_PATH);
        if (($data['type'] ?? null) === 'sale'
            || (is_string($path) && (str_starts_with($path, '/sales') || str_starts_with($path, '/pos')))) {
            return 'order';
        }
        if (($data['type'] ?? null) === 'repair'
            || (is_string($path) && (str_starts_with($path, '/repairs') || preg_match('#^/records/repair/\d+$#', $path) === 1))) {
            return 'repair';
        }

        return 'system';
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->delete();

        return response()->json(['success' => true]);
    }
}
