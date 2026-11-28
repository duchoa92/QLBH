<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ServerNetwork;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Mục "Mạng & địa chỉ IP" trong Cài đặt: xem IP dùng được/không dùng được và đổi IP máy chủ. */
class ServerNetworkController extends Controller
{
    /** Quét mạng: dải IP, IP không thể chọn, IP còn trống, gợi ý. */
    public function scan(): JsonResponse
    {
        $support = ServerNetwork::support();
        if (! $support['supported']) {
            return response()->json(['supported' => false, 'message' => $support['message']]);
        }

        return response()->json(['supported' => true] + ServerNetwork::scan() + ['status' => ServerNetwork::status()]);
    }

    /** Kiểm tra nhanh một IP trước khi đổi. */
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate(['ip' => ['required', 'ipv4']]);
        if (! ServerNetwork::support()['supported']) {
            return response()->json(['ok' => false, 'reason' => 'Máy chủ chưa cài trợ giúp đổi IP.'], 422);
        }

        return response()->json(ServerNetwork::check($data['ip']));
    }

    /** Gửi yêu cầu đổi IP. Việc đổi chạy nền; kết quả hỏi qua status(). */
    public function apply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ip' => ['required', 'ipv4'],
            'gateway' => ['nullable', 'ipv4'],
            'dns' => ['nullable', 'string', 'max:100', 'regex:/^(\d{1,3}(\.\d{1,3}){3})(,\d{1,3}(\.\d{1,3}){3}){0,3}$/'],
        ], [
            'ip.ipv4' => 'IP không hợp lệ. Ví dụ: 192.168.100.210.',
            'gateway.ipv4' => 'Gateway không hợp lệ.',
            'dns.regex' => 'DNS gồm các địa chỉ IP cách nhau bằng dấu phẩy.',
        ]);

        $support = ServerNetwork::support();
        if (! $support['supported']) {
            return response()->json(['message' => $support['message']], 422);
        }
        $last = ServerNetwork::status();
        if (($last['state'] ?? null) === 'running' && (time() - (int) ($last['time'] ?? 0)) < 300) {
            return response()->json(['message' => 'Đang có một lần đổi IP chạy, vui lòng đợi nó xong.'], 409);
        }

        $check = ServerNetwork::check($data['ip']);
        if (! ($check['ok'] ?? false)) {
            return response()->json(['message' => 'Không thể dùng IP '.$data['ip'].': '.($check['reason'] ?? $check['error'] ?? 'không rõ lý do').'.'], 422);
        }
        if (! ServerNetwork::request($data['ip'], $data['gateway'] ?? null, $data['dns'] ?? null)) {
            return response()->json(['message' => 'Không gửi được yêu cầu đổi IP. Kiểm tra: sudo bash scripts/install-network-helper.sh'], 500);
        }

        return response()->json(['started' => true, 'new_ip' => $data['ip']], 202);
    }

    /** Trang hỏi mỗi vài giây trong lúc đổi IP. */
    public function status(): JsonResponse
    {
        return response()->json(['status' => ServerNetwork::status()]);
    }
}
