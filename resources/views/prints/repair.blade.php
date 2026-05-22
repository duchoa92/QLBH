<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <title>
        {{ $repair->code }}
    </title>

    <style>

        * {

            box-sizing: border-box;

            font-family: Arial, sans-serif;
        }

        body {

            margin: 0;

            padding: 30px;

            color: #111827;

            background: white;
        }

        .container {

            max-width: 900px;

            margin: auto;
        }

        .header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            border-bottom: 2px solid #e5e7eb;

            padding-bottom: 20px;

            margin-bottom: 30px;
        }

        .title {

            font-size: 28px;

            font-weight: bold;
        }

        .code {

            font-size: 18px;

            color: #2563eb;

            font-weight: bold;
        }

        .section {

            margin-bottom: 30px;
        }

        .section-title {

            font-size: 18px;

            font-weight: bold;

            margin-bottom: 15px;

            border-left: 5px solid #2563eb;

            padding-left: 12px;
        }

        .grid {

            display: grid;

            grid-template-columns: repeat(2, 1fr);

            gap: 15px;
        }

        .item {

            border: 1px solid #e5e7eb;

            border-radius: 10px;

            padding: 14px;
        }

        .label {

            font-size: 13px;

            color: #6b7280;

            margin-bottom: 5px;
        }

        .value {

            font-size: 15px;

            font-weight: bold;
        }

        .issues {

            display: flex;

            flex-wrap: wrap;

            gap: 10px;
        }

        .issue {

            background: #fee2e2;

            color: #b91c1c;

            padding: 8px 14px;

            border-radius: 999px;

            font-size: 14px;

            font-weight: bold;
        }

        .images {

            display: grid;

            grid-template-columns: repeat(3, 1fr);

            gap: 15px;
        }

        .images img {

            width: 100%;

            height: 180px;

            object-fit: cover;

            border-radius: 12px;

            border: 1px solid #e5e7eb;
        }

        .signature {

            display: grid;

            grid-template-columns: repeat(2, 1fr);

            gap: 80px;

            margin-top: 80px;
        }

        .sign-box {

            text-align: center;
        }

        .sign-title {

            font-weight: bold;

            margin-bottom: 80px;
        }

        .print-btn {

            position: fixed;

            right: 20px;

            top: 20px;

            padding: 12px 20px;

            border: none;

            border-radius: 10px;

            background: #2563eb;

            color: white;

            cursor: pointer;

            font-size: 15px;
        }

        @media print {

            .print-btn {

                display: none;
            }

            body {

                padding: 0;
            }
        }

    </style>

</head>

<body>

<button
    class="print-btn"
    onclick="window.print()"
>
    In phiếu
</button>

<div class="container">

    <!-- HEADER -->

    <div class="header">

        <div>

            <div class="title">
                PHIẾU NHẬN SỬA CHỮA
            </div>

            <div style="margin-top: 8px; color: #6b7280;">
                Ngày nhận:
                {{ $repair->created_at }}
            </div>

        </div>

        <div class="code">
            {{ $repair->code }}
        </div>

    </div>

    <!-- CUSTOMER -->

    <div class="section">

        <div class="section-title">
            Thông tin khách hàng
        </div>

        <div class="grid">

            <div class="item">

                <div class="label">
                    Khách hàng
                </div>

                <div class="value">
                    {{ $repair->customer_name }}
                </div>

            </div>

            <div class="item">

                <div class="label">
                    Số điện thoại
                </div>

                <div class="value">
                    {{ $repair->customer_phone }}
                </div>

            </div>

        </div>

    </div>

    <!-- DEVICE -->

    <div class="section">

        <div class="section-title">
            Thông tin thiết bị
        </div>

        <div class="grid">

            <div class="item">

                <div class="label">
                    Thiết bị
                </div>

                <div class="value">
                    {{ $repair->device_name }}
                </div>

            </div>

            <div class="item">

                <div class="label">
                    IMEI / Serial
                </div>

                <div class="value">
                    {{ $repair->imei ?: '---' }}
                </div>

            </div>

            <div class="item">

                <div class="label">
                    Mật khẩu màn hình
                </div>

                <div class="value">
                    {{ $repair->screen_password ?: '---' }}
                </div>

            </div>

            <div class="item">

                <div class="label">
                    Chi phí dự kiến
                </div>

                <div class="value">
                    {{ number_format((float) $repair->estimated_cost) }}
                    đ
                </div>

            </div>

        </div>

    </div>

    <!-- ISSUE -->

    <div class="section">

        <div class="section-title">
            Tình trạng máy
        </div>

        <div class="issues">

            @foreach($repair->issue ?? [] as $issue)

                <div class="issue">
                    {{ $issue }}
                </div>

            @endforeach

        </div>

    </div>

    <!-- REQUEST -->

    <div class="section">

        <div class="section-title">
            Yêu cầu sửa chữa
        </div>

        <div class="item">

            {{ $repair->repair_request ?: '---' }}

        </div>

    </div>

    <!-- ACCESSORIES -->

    <div class="section">

        <div class="section-title">
            Phụ kiện kèm theo
        </div>

        <div class="item">

            {{ implode(', ', $repair->accessories ?? []) ?: '---' }}

        </div>

    </div>

    <!-- NOTE -->

    <div class="section">

        <div class="section-title">
            Ghi chú
        </div>

        <div class="item">

            {{ $repair->note ?: '---' }}

        </div>

    </div>

    <!-- IMAGES -->

    @if($repair->images->count())

        <div class="section">

            <div class="section-title">
                Hình ảnh tình trạng máy
            </div>

            <div class="images">

                @foreach($repair->images as $image)

                    <img
                        src="{{ asset('storage/' . $image->image_path) }}"
                    >

                @endforeach

            </div>

        </div>

    @endif

    <!-- SIGN -->

    <div class="signature">

        <div class="sign-box">

            <div class="sign-title">
                Khách hàng
            </div>

            <div>
                (Ký và ghi rõ họ tên)
            </div>

        </div>

        <div class="sign-box">

            <div class="sign-title">
                Nhân viên tiếp nhận
            </div>

            <div>
                (Ký và ghi rõ họ tên)
            </div>

        </div>

    </div>

</div>

</body>
</html>