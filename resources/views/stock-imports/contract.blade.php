<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hợp đồng mua bán {{ $import->code }}</title>
    <style>
        @page { size: A4; margin: 18mm; }
        * { box-sizing: border-box; }
        body { color: #111827; font: 13px/1.55 Arial, sans-serif; margin: 0 auto; max-width: 900px; }
        h1, h2 { text-align: center; }
        h1 { font-size: 20px; margin: 0; text-transform: uppercase; }
        h2 { font-size: 14px; font-weight: 400; margin: 3px 0 22px; }
        .party { margin: 16px 0; }
        .party p { margin: 3px 0; }
        table { border-collapse: collapse; margin: 18px 0; width: 100%; }
        th, td { border: 1px solid #555; padding: 7px; vertical-align: top; }
        th { background: #f3f4f6; text-align: center; }
        .num { text-align: right; white-space: nowrap; }
        .total { font-size: 15px; font-weight: 700; text-align: right; }
        .signatures { display: grid; gap: 40px; grid-template-columns: 1fr 1fr; margin-top: 58px; text-align: center; }
        .signatures strong, .signatures span { display: block; }
        .signatures span { font-size: 11px; font-style: italic; }
        .toolbar { margin: 16px auto; max-width: 900px; text-align: right; }
        .toolbar button { background: #1d4ed8; border: 0; border-radius: 6px; color: white; cursor: pointer; padding: 9px 14px; }
        @media print { .toolbar { display: none; } body { max-width: none; } }
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">In hợp đồng</button></div>
    <h1>Hợp đồng mua bán hàng hóa</h1>
    <h2>Số: {{ $import->code }}</h2>
    <p>Hôm nay, ngày {{ optional($import->import_date)->format('d/m/Y') }}, các bên gồm:</p>

    <div class="party">
        <p><strong>BÊN MUA (CỬA HÀNG):</strong> {{ config('app.name', 'Cửa hàng') }}</p>
        <p>Đại diện: {{ $import->user?->name ?: 'Đại diện cửa hàng' }}</p>
    </div>
    <div class="party">
        <p><strong>BÊN BÁN:</strong> {{ $import->party_name ?: ($import->customer?->full_name ?: ($import->supplier?->name ?: '—')) }}</p>
        <p>Số điện thoại: {{ $import->party_phone ?: ($import->customer?->phone ?: ($import->supplier?->phone ?: '—')) }}</p>
        <p>CCCD: {{ $import->party_cccd ?: ($import->customer?->cccd ?: '—') }}</p>
        <p>Địa chỉ: {{ $import->party_address ?: ($import->customer?->address ?: ($import->supplier?->address ?: '—')) }}</p>
    </div>

    <p>Hai bên thống nhất việc mua bán hàng hóa với nội dung sau:</p>
    <table>
        <thead><tr><th>STT</th><th>Tên hàng / thông tin</th><th>ĐVT</th><th>Số lượng</th><th>Đơn giá</th><th>Thành tiền</th></tr></thead>
        <tbody>
        @foreach($import->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->product?->name ?: 'Sản phẩm' }}@if($item->variant)<br><small>{{ collect($item->variant->attributes ?: [])->map(fn($value) => is_array($value) ? ($value['value'] ?? $value['label'] ?? $value['name'] ?? '') : $value)->filter()->join(' / ') ?: $item->variant->sku }}</small>@endif</td>
                <td>{{ $item->unit_name ?: ($item->unit?->short_name ?: '—') }}</td>
                <td class="num">{{ number_format((float) $item->quantity, 0, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $item->cost_price, 0, ',', '.') }} đ</td>
                <td class="num">{{ number_format((float) $item->quantity * (float) $item->cost_price, 0, ',', '.') }} đ</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <p class="total">Tiền hàng: {{ number_format((float) $import->total_amount, 0, ',', '.') }} đ</p>
    <p class="num">Giảm giá: {{ number_format((float) $import->discount, 0, ',', '.') }} đ</p>
    <p class="num">Phí khác: {{ number_format((float) $import->extra_fee, 0, ',', '.') }} đ</p>
    <p class="total">Tổng thanh toán: {{ number_format((float) $import->grand_total, 0, ',', '.') }} đ</p>
    @if($import->note)<p>Ghi chú: {{ $import->note }}</p>@endif
    <p>Hai bên xác nhận hàng hóa và giá trị nêu trên, cùng ký tên dưới đây.</p>
    <div class="signatures"><div><strong>BÊN MUA</strong><span>(Ký, ghi rõ họ tên)</span></div><div><strong>BÊN BÁN</strong><span>(Ký, ghi rõ họ tên)</span><br><br>{{ $import->party_name ?: ($import->customer?->full_name ?: ($import->supplier?->name ?: '')) }}</div></div>
</body>
</html>
