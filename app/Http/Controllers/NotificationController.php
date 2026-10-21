<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->latest()->limit(30)->get();

        return response()->json([
            'notifications' => $notifications->map(fn ($notification): array => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'Thông báo hệ thống',
                'message' => $notification->data['message'] ?? '',
                'type' => $notification->data['type'] ?? 'info',
                'url' => $this->internalPath($notification->data['url'] ?? null),
                'read' => $notification->read_at !== null,
                'time' => $notification->created_at?->diffForHumans(),
            ]),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    private function internalPath(mixed $url): ?string
    {
        if (!is_string($url) || $url === '') return null;
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || !str_starts_with($path, '/') || str_starts_with($path, '//')) return null;
        $query = parse_url($url, PHP_URL_QUERY);
        return $path.($query ? '?'.$query : '');
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
