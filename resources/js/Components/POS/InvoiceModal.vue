<script setup>
import { computed } from 'vue'

const props = defineProps({
    show: Boolean,
    data: Object, // Nhận dữ liệu từ component cha gửi vào
})

const emit = defineEmits(['close'])

// Hàm định dạng số thành tiền mặt (Ví dụ: 2500000 -> 2.500.000)
const format = (value) => {
    if (!value && value !== 0) return '0'
    return new Intl.NumberFormat('vi-VN').format(value)
}

const printInvoice = () => {
    const printContent = document.getElementById('invoice-print')
    const win = window.open('', '', 'width=400,height=600')

    win.document.write(`
        <html>
            <head>
                <title>In hóa đơn</title>
                <style>
                    @page {
                        size: 80mm auto;
                        margin: 0;
                    }
                    body {
                        font-family: 'Courier New', Courier, monospace;
                        font-size: 13px;
                        line-height: 1.4;
                        color: #000;
                        margin: 0;
                        padding: 4mm;
                        width: 72mm;
                    }
                    .center { text-align: center; }
                    .right { text-align: right; }
                    .bold { font-weight: bold; }
                    .uppercase { text-transform: uppercase; }
                    
                    .line-equal { border-top: 1px dashed #000; margin: 8px 0; }
                    .line-double { border-top: 3px double #000; margin: 8px 0; }
                    
                    .invoice-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
                    .invoice-table th { font-weight: bold; padding-bottom: 5px; }
                    .invoice-table td { padding: 4px 0; vertical-align: top; }
                    
                    .total-section { margin-top: 10px; }
                    .total-row { display: flex; justify-content: space-between; padding: 3px 0; }
                    .grand-total { font-size: 14px; font-weight: bold; }
                </style>
            </head>
            <body>
                ${printContent.innerHTML}
                <script>
                    window.onload = function() {
                        window.focus();
                        window.print();
                        window.close();
                    }
                <\/script>
            </body>
        </html>
    `)

    win.document.close()
}
</script>

<template>
<div v-if="show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white w-[360px] rounded-lg shadow-xl p-4 text-black">

        <div id="invoice-print" style="font-family: 'Courier New', Courier, monospace; font-size: 13px;">
            
            <div class="center">
                <div class="bold" style="font-size: 16px;">ĐIỆN THOẠI - MÁY TÍNH ĐỨC HÒA</div>
                <div style="font-size: 11px; margin-top: 2px;">Đ/C: Cầu Giớ, Vạn Xuân, Hưng Yên</div>
                <div style="font-size: 11px;">Điện thoại: 0906.064.789</div>
            </div>

            <div class="line-double"></div>

            <div class="center bold uppercase" style="font-size: 15px; margin: 10px 0;">
                Hóa đơn bán lẻ
            </div>

            <div style="font-size: 12px; line-height: 1.5;">
                <div>Mã HD: {{ data.code }}</div>
                <div>Ngày: {{ new Date(data.created_at).toLocaleString('vi-VN') }}</div>
                <div>Tên KH: {{ data.customer?.full_name || 'Khách lẻ' }}</div>
            </div>

            <table class="invoice-table">
                <thead>
                    <tr style="border-bottom: 1px dashed #000;">
                        <th align="left" style="width: 45%;">Tên hàng</th>
                        <th align="center" style="width: 10%;">SL</th>
                        <th align="right" style="width: 20%;">Đ.Giá</th>
                        <th align="right" style="width: 25%;">T.Tiền</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="item in data.items" :key="item.id">
                        <tr>
                            <td colspan="4" class="bold" style="padding-top: 6px; padding-bottom: 0;">
                                {{ item.product?.name }}
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px dotted #eee;">
                            <td></td> <td align="center">{{ item.quantity }}</td>
                            <td align="right">{{ format(item.unit_price) }}</td>
                            <td align="right" class="bold">
                                {{ format(item.quantity * item.unit_price) }}
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>

            <div class="line-equal"></div>

            <div class="total-section">
                <div class="total-row">
                    <span>Tổng cộng: </span>
                    <span>{{ format(data.subtotal || data.grand_total) }}</span>
                </div>
                
                <div class="total-row">
                    <span>GIẢM GIÁ:</span>
                    <span>-{{ format(data.discount || 0) }}</span>
                </div>
                
                <div class="line-equal" style="border-top-style: dotted;"></div>
                
                <div class="total-row grand-total">
                    <span>KHÁCH PHẢI TRẢ:</span>
                    <span>{{ format(data.grand_total) }}</span>
                </div>
                
                <div class="line-equal" style="border-top-style: dotted;"></div>
                
                <div class="total-row">
                    <span>Tiền khách đưa:</span>
                    <span>{{ format(data.paid) }}</span>
                </div>
                <div class="total-row">
                    <span>Tiền thừa:</span>
                    <span>{{ format(data.change) }}</span>
                </div>
            </div>

            <div class="total-row">
                <span>Thanh toán:</span>
                <span>
                    {{
                        data.payment_method === 'cash' ? 'Tiền mặt' :
                        data.payment_method === 'bank' ? 'Chuyển khoản' :
                        'Thẻ'
                    }}
                </span>
            </div>

            <div class="line-equal"></div>

            <div class="center bold" style="font-size: 12px; margin-top: 10px;">
                CẢM ƠN QUÝ KHÁCH!<br>
                Hẹn gặp lại quý khách!
            </div>

        </div>

        <div class="mt-5 flex gap-2">
            <button
                @click="printInvoice"
                class="flex-1 bg-green-600 hover:bg-green-700 text-white font-medium py-2.5 rounded transition-colors"
            >
                🖨️ In hóa đơn
            </button>

            <button
                @click="$emit('close')"
                class="w-24 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2.5 rounded transition-colors"
            >
                Đóng
            </button>
        </div>

    </div>
</div>
</template>