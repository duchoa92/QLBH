<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\BackupCloudConnection;
use App\Services\BackupService;
use App\Services\BackupRestoreService;
use App\Services\BackupOperationProgress;
use App\Services\CloudBackupStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    private function backupSettingsRedirect()
    {
        return redirect()->route('settings.index', ['section' => 'backups']);
    }

    public function index(CloudBackupStorage $cloudStorage)
    {
        return redirect()->route('settings.index', ['section' => 'backups']);
    }

    public function pageData(CloudBackupStorage $cloudStorage): array
    {
        $directory = storage_path('app/backups');
        $backups = collect(is_dir($directory) ? File::files($directory) : [])
            ->filter(fn ($file) => preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}(?:-\d{6})?\.zip$/', $file->getFilename()))
            ->sortByDesc(fn ($file) => $file->getFilename())
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'created_at' => date('Y-m-d H:i:s', $file->getMTime()),
            ])->values();

        $cloudConnections = BackupCloudConnection::query()->orderBy('name')->get()->map(function ($connection) use ($cloudStorage) {
            try {
                return ['id' => $connection->id, 'name' => $connection->name, 'provider' => $connection->provider, 'enabled' => $connection->enabled, 'last_tested_at' => $connection->last_tested_at?->toDateTimeString(), 'backups' => $cloudStorage->backups($connection), 'error' => null];
            } catch (Throwable $exception) {
                return ['id' => $connection->id, 'name' => $connection->name, 'provider' => $connection->provider, 'enabled' => $connection->enabled, 'last_tested_at' => $connection->last_tested_at?->toDateTimeString(), 'backups' => [], 'error' => $exception->getMessage()];
            }
        })->values();

        return [
            'backups' => $backups,
            'cloud_connections' => $cloudConnections,
            'cloud_oauth' => [
                'google_drive' => filled(config('services.google_drive.client_id')) && filled(config('services.google_drive.client_secret')),
                'onedrive' => filled(config('services.microsoft_onedrive.client_id')) && filled(config('services.microsoft_onedrive.client_secret')),
                'google_redirect_uri' => route('backups.cloud.oauth.callback', ['provider' => 'google_drive']),
                'onedrive_redirect_uri' => route('backups.cloud.oauth.callback', ['provider' => 'onedrive']),
            ],
            'restore_preview' => session()->get('restore_preview'),
            'schedule' => [
                'enabled' => (bool) setting('backup_enabled', false),
                'frequency' => setting('backup_frequency', 'daily'),
                'time' => setting('backup_time', '02:00'),
                'weekday' => (int) setting('backup_weekday', 1),
                'monthday' => (int) setting('backup_monthday', 1),
                'timezone' => setting('backup_timezone', 'Asia/Ho_Chi_Minh'),
                'last_run' => setting('backup_last_run'),
                'last_error' => setting('backup_last_error'),
            ],
        ];
    }

    public function store(Request $request, BackupService $service, BackupOperationProgress $progress, CloudBackupStorage $cloudStorage)
    {
        $operation = $request->validate(['operation_id' => ['required', 'uuid']])['operation_id'];
        $userId = (int) $request->user()->id;
        $update = fn ($percent, $message) => $progress->update($userId, $operation, $percent, $message);
        $progress->update($userId, $operation, 1, 'Bắt đầu tạo bản sao lưu.');

        try {
            $connections = BackupCloudConnection::where('enabled', true)->get();
            $path = $service->create(fn ($percent, $message) => $update($connections->isEmpty() ? $percent : (int) ($percent * 0.6), $message));
            foreach ($connections as $index => $connection) {
                $cloudStorage->upload($connection, $path, basename($path), fn ($percent, $message) => $update(60 + (int) ((($index + $percent / 100) / max(1, $connections->count())) * 39), $message));
            }
            Setting::set('backup_last_run', now()->toDateTimeString());
            Setting::set('backup_last_error', null);
            $progress->update($userId, $operation, 100, 'Tạo bản sao lưu hoàn tất.', 'completed');
            return $this->backupSettingsRedirect()->with('success', 'Đã tạo bản sao lưu thành công.');
        } catch (Throwable $exception) {
            $progress->update($userId, $operation, 100, 'Tạo bản sao lưu thất bại.', 'failed');
            throw $exception;
        }
    }

    public function updateSchedule(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'frequency' => ['required', 'in:daily,weekly,monthly'],
            'time' => ['required', 'date_format:H:i'],
            'weekday' => ['required', 'integer', 'between:0,6'],
            'monthday' => ['required', 'integer', 'between:1,31'],
            'timezone' => ['required', 'in:Asia/Ho_Chi_Minh,Asia/Bangkok,UTC'],
        ]);

        foreach ($data as $key => $value) {
            Setting::set('backup_'.($key === 'enabled' ? 'enabled' : $key), $value);
        }

        return $this->backupSettingsRedirect()->with('success', 'Đã lưu lịch sao lưu tự động.');
    }

    public function inspect(Request $request, string $backup, BackupRestoreService $restoreService, BackupOperationProgress $progress)
    {
        $operation = $request->validate(['operation_id' => ['required', 'uuid']])['operation_id'];
        $userId = (int) $request->user()->id;
        $update = fn ($percent, $message) => $progress->update($userId, $operation, $percent, $message);
        try {
            $progress->update($userId, $operation, 1, 'Đang kiểm tra tệp sao lưu.');
            $preview = $restoreService->inspect($backup, $update);
            $progress->update($userId, $operation, 100, 'Đối chiếu hoàn tất.', 'completed');
            return $this->backupSettingsRedirect()->with('restore_preview', $preview)->with('success', 'Đã kiểm tra tính toàn vẹn và đối chiếu cấu trúc bản sao lưu.');
        } catch (Throwable $exception) {
            $progress->update($userId, $operation, 100, 'Kiểm tra bản sao lưu thất bại.', 'failed');
            return $this->backupSettingsRedirect()->with('error', 'Không thể kiểm tra bản sao lưu: '.$exception->getMessage());
        }
    }

    public function import(Request $request, BackupRestoreService $restoreService, BackupOperationProgress $progress)
    {
        $data = $request->validate([
            'backup_file' => ['required', 'file', 'mimes:zip', 'max:524288'],
            'operation_id' => ['required', 'uuid'],
        ]);
        $operation = $data['operation_id'];
        $userId = (int) $request->user()->id;
        $progress->update($userId, $operation, 1, 'Đang nhận tệp sao lưu.');

        $filename = 'backup_'.now()->format('Y-m-d_H-i-s-u').'.zip';
        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory);
        $data['backup_file']->move($directory, $filename);

        try {
            $progress->update($userId, $operation, 35, 'Đã tải tệp lên; đang kiểm tra cấu trúc và dữ liệu.');
            $preview = $restoreService->inspect($filename, fn ($percent, $message) => $progress->update($userId, $operation, 35 + (int) ($percent * 0.64), $message));
            $progress->update($userId, $operation, 100, 'Tải lên và đối chiếu hoàn tất.', 'completed');
            return $this->backupSettingsRedirect()->with('restore_preview', $preview)->with('success', 'Đã tải tệp lên và kiểm tra cấu trúc bản sao lưu.');
        } catch (Throwable $exception) {
            File::delete($directory.DIRECTORY_SEPARATOR.$filename);
            $progress->update($userId, $operation, 100, 'Tệp sao lưu không hợp lệ.', 'failed');
            return $this->backupSettingsRedirect()->with('error', 'Tệp không phải bản sao lưu hợp lệ: '.$exception->getMessage());
        }
    }

    public function restore(Request $request, string $backup, BackupRestoreService $restoreService, BackupService $backupService, BackupOperationProgress $progress)
    {
        $data = $request->validate([
            'mode' => ['required', 'in:replace,merge'],
            'conflict_policy' => ['required', 'in:keep_current,use_backup'],
            'confirmed' => ['required', 'accepted'],
            'operation_id' => ['required', 'uuid'],
        ]);
        $operation = $data['operation_id'];
        $userId = (int) $request->user()->id;
        $update = fn ($percent, $message) => $progress->update($userId, $operation, $percent, $message);

        try {
            $update(1, 'Bắt đầu kiểm tra dữ liệu để khôi phục.');
            $result = $restoreService->restore($backup, $data['mode'], $data['conflict_policy'], $backupService, $update);
            Setting::set('backup_last_run', now()->toDateTimeString());
            Setting::set('backup_last_error', null);
            $message = $result['mode'] === 'replace'
                ? 'Đã khôi phục toàn bộ cơ sở dữ liệu. Bản dữ liệu trước đó được lưu làm điểm phục hồi.'
                : 'Đã gộp dữ liệu từ bản sao lưu theo lựa chọn xử lý xung đột.';
            $update(100, 'Khôi phục hoàn tất.', 'completed');
            return $this->backupSettingsRedirect()->with('success', $message)->with('restore_preview', null);
        } catch (Throwable $exception) {
            $update(100, 'Khôi phục thất bại; kiểm tra thông báo để biết chi tiết.', 'failed');
            return $this->backupSettingsRedirect()->with('error', 'Khôi phục chưa hoàn tất: '.$exception->getMessage());
        }
    }

    public function progress(Request $request, string $operation, BackupOperationProgress $progress)
    {
        return response()->json($progress->get((int) $request->user()->id, $operation));
    }

    public function storeCloudConnection(Request $request, CloudBackupStorage $cloudStorage)
    {
        $provider = $request->input('provider');
        if ($provider === 's3') {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:100'], 'provider' => ['required', 'in:s3'],
                'key' => ['required', 'string', 'max:255'], 'secret' => ['required', 'string', 'max:255'],
                'region' => ['nullable', 'string', 'max:100'], 'bucket' => ['required', 'string', 'max:255'],
                'endpoint' => ['nullable', 'url', 'max:500'], 'base_path' => ['nullable', 'string', 'max:255'],
                'path_style' => ['nullable', 'boolean'],
            ]);
            $configuration = [
                'key' => $data['key'], 'secret' => $data['secret'], 'region' => $data['region'] ?? 'auto',
                'bucket' => $data['bucket'], 'endpoint' => $data['endpoint'] ?? '', 'base_path' => $data['base_path'] ?? 'backups',
                'path_style' => $data['path_style'] ?? false,
            ];
        } else {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:100'], 'provider' => ['required', 'in:webdav'],
                'url' => ['required', 'url', 'max:500'], 'username' => ['required', 'string', 'max:255'],
                'token' => ['required', 'string', 'max:1000'], 'base_path' => ['nullable', 'string', 'max:255'],
            ]);
            $configuration = ['url' => rtrim($data['url'], '/'), 'username' => $data['username'], 'token' => $data['token'], 'base_path' => $data['base_path'] ?? 'backups'];
        }

        $connection = new BackupCloudConnection(['name' => $data['name'], 'provider' => $data['provider'], 'configuration' => $configuration]);
        try {
            $cloudStorage->test($connection);
        } catch (Throwable $exception) {
            return $this->backupSettingsRedirect()->with('error', 'Kết nối chưa thành công: '.$exception->getMessage())->withInput($request->except(['key', 'secret', 'token']));
        }
        $connection->enabled = true;
        $connection->last_tested_at = now();
        $connection->save();

        return $this->backupSettingsRedirect()->with('success', 'Đã kiểm tra và kết nối thành công với '.$connection->name.'.');
    }

    public function destroyCloudConnection(BackupCloudConnection $connection)
    {
        $connection->delete();
        return $this->backupSettingsRedirect()->with('success', 'Đã xóa kết nối đám mây.');
    }

    public function toggleCloudConnection(BackupCloudConnection $connection)
    {
        $connection->enabled = ! $connection->enabled;
        $connection->save();
        return $this->backupSettingsRedirect()->with('success', $connection->enabled ? 'Đã bật sao lưu lên '.$connection->name.'.' : 'Đã tạm dừng sao lưu lên '.$connection->name.'.');
    }

    public function inspectCloudBackup(Request $request, BackupCloudConnection $connection, CloudBackupStorage $cloudStorage, BackupRestoreService $restoreService, BackupOperationProgress $progress)
    {
        $data = $request->validate(['name' => ['required', 'string'], 'operation_id' => ['required', 'uuid']]);
        $userId = (int) $request->user()->id;
        $operation = $data['operation_id'];
        $localName = 'backup_'.now()->format('Y-m-d_H-i-s-u').'.zip';
        $localPath = storage_path('app/backups/'.$localName);
        File::ensureDirectoryExists(dirname($localPath));
        try {
            $progress->update($userId, $operation, 5, 'Đang tải bản sao lưu từ '.$connection->name.'.');
            $cloudStorage->download($connection, $data['name'], $localPath, fn ($percent, $message) => $progress->update($userId, $operation, 5 + (int) ($percent * 0.44), $message));
            $preview = $restoreService->inspect($localName, fn ($percent, $message) => $progress->update($userId, $operation, 50 + (int) ($percent * 0.49), $message));
            $progress->update($userId, $operation, 100, 'Đã tải xuống và đối chiếu xong.', 'completed');
            return $this->backupSettingsRedirect()->with('restore_preview', $preview)->with('success', 'Đã tải bản sao lưu từ đám mây và kiểm tra dữ liệu.');
        } catch (Throwable $exception) {
            File::delete($localPath);
            $progress->update($userId, $operation, 100, 'Không thể tải hoặc kiểm tra bản sao lưu đám mây.', 'failed');
            return $this->backupSettingsRedirect()->with('error', 'Không thể kiểm tra bản sao lưu đám mây: '.$exception->getMessage());
        }
    }

    public function destroyCloudBackup(Request $request, BackupCloudConnection $connection, CloudBackupStorage $cloudStorage)
    {
        $data = $request->validate(['name' => ['required', 'string']]);
        $cloudStorage->delete($connection, $data['name']);
        return $this->backupSettingsRedirect()->with('success', 'Đã xóa bản sao lưu trên '.$connection->name.'.');
    }

    public function download(string $backup): BinaryFileResponse
    {
        abort_unless(preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}(?:-\d{6})?\.zip$/', $backup), 404);
        $path = storage_path('app/backups/'.$backup);
        abort_unless(is_file($path), 404);

        return response()->download($path);
    }

    public function destroy(Request $request, string $backup, BackupOperationProgress $progress)
    {
        abort_unless(preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}(?:-\d{6})?\.zip$/', $backup), 404);
        $path = storage_path('app/backups/'.$backup);
        abort_unless(is_file($path), 404);
        $operation = $request->validate(['operation_id' => ['required', 'uuid']])['operation_id'];
        $progress->update((int) $request->user()->id, $operation, 35, 'Đang xóa tệp sao lưu.');
        File::delete($path);
        $progress->update((int) $request->user()->id, $operation, 100, 'Đã xóa tệp sao lưu.', 'completed');

        return $this->backupSettingsRedirect()->with('success', 'Đã xóa bản sao lưu.');
    }
}
