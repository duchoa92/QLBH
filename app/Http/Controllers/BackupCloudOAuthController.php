<?php

namespace App\Http\Controllers;

use App\Models\BackupCloudConnection;
use App\Services\CloudBackupStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Throwable;

class BackupCloudOAuthController extends Controller
{
    public function start(Request $request, string $provider)
    {
        abort_unless(in_array($provider, ['google_drive', 'onedrive'], true), 404);
        $service = $provider === 'google_drive' ? 'google_drive' : 'microsoft_onedrive';
        $clientId = config("services.{$service}.client_id");
        if (! $clientId || ! config("services.{$service}.client_secret")) {
            return back()->with('error', 'Chưa cấu hình OAuth client ID/secret trên máy chủ cho dịch vụ này.');
        }

        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $state = bin2hex(random_bytes(32));
        session()->put('backup_cloud_oauth', ['provider' => $provider, 'name' => $data['name'], 'state' => $state]);
        $redirectUri = route('backups.cloud.oauth.callback', ['provider' => $provider]);

        if ($provider === 'google_drive') {
            $url = 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
                'client_id' => $clientId, 'redirect_uri' => $redirectUri, 'response_type' => 'code',
                'scope' => 'https://www.googleapis.com/auth/drive.file', 'access_type' => 'offline',
                'prompt' => 'consent', 'include_granted_scopes' => 'true', 'state' => $state,
            ]);
        } else {
            $tenant = config('services.microsoft_onedrive.tenant', 'common');
            $url = 'https://login.microsoftonline.com/'.rawurlencode($tenant).'/oauth2/v2.0/authorize?'.http_build_query([
                'client_id' => $clientId, 'redirect_uri' => $redirectUri, 'response_type' => 'code',
                'response_mode' => 'query', 'scope' => 'offline_access Files.ReadWrite', 'state' => $state,
            ]);
        }

        return Inertia::location($url);
    }

    public function callback(Request $request, string $provider, CloudBackupStorage $cloudStorage)
    {
        abort_unless(in_array($provider, ['google_drive', 'onedrive'], true), 404);
        $pending = session()->pull('backup_cloud_oauth');
        if (! is_array($pending) || ($pending['provider'] ?? null) !== $provider || ! hash_equals((string) ($pending['state'] ?? ''), (string) $request->query('state'))) {
            return redirect()->route('backups.index')->with('error', 'Phiên kết nối đám mây hết hạn hoặc không hợp lệ. Hãy thử kết nối lại.');
        }
        if ($request->filled('error')) {
            return redirect()->route('backups.index')->with('error', 'Dịch vụ đám mây từ chối cấp quyền: '.$request->query('error_description', $request->query('error')));
        }
        if (! $request->filled('code')) return redirect()->route('backups.index')->with('error', 'Dịch vụ đám mây không trả mã ủy quyền.');

        $service = $provider === 'google_drive' ? 'google_drive' : 'microsoft_onedrive';
        $redirectUri = route('backups.cloud.oauth.callback', ['provider' => $provider]);
        $tokenUrl = $provider === 'google_drive'
            ? 'https://oauth2.googleapis.com/token'
            : 'https://login.microsoftonline.com/'.rawurlencode(config('services.microsoft_onedrive.tenant', 'common')).'/oauth2/v2.0/token';
        try {
            $tokenResponse = Http::asForm()->timeout(30)->post($tokenUrl, [
                'client_id' => config("services.{$service}.client_id"),
                'client_secret' => config("services.{$service}.client_secret"),
                'code' => $request->query('code'), 'grant_type' => 'authorization_code', 'redirect_uri' => $redirectUri,
                ...($provider === 'onedrive' ? ['scope' => 'offline_access Files.ReadWrite'] : []),
            ]);
            if (! $tokenResponse->successful()) throw new \RuntimeException('Không đổi được OAuth code thành access token (HTTP '.$tokenResponse->status().').');
            $tokens = $tokenResponse->json();
            if (empty($tokens['access_token']) || empty($tokens['refresh_token'])) throw new \RuntimeException('Dịch vụ không trả refresh token. Hãy kiểm tra quyền offline access của OAuth app.');

            $connection = new BackupCloudConnection([
                'name' => $pending['name'], 'provider' => $provider,
                'configuration' => [
                    'access_token' => $tokens['access_token'], 'refresh_token' => $tokens['refresh_token'],
                    'token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600))->timestamp,
                ],
            ]);
            $cloudStorage->test($connection);
            $connection->enabled = true;
            $connection->last_tested_at = now();
            $connection->save();

            return redirect()->route('backups.index')->with('success', 'Đã kết nối '.$pending['name'].' thành công.');
        } catch (Throwable $exception) {
            report($exception);
            return redirect()->route('backups.index')->with('error', 'Không thể kết nối '.$pending['name'].': '.$exception->getMessage());
        }
    }
}
