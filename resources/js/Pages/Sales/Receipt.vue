<script setup>
import { onMounted } from 'vue'

defineOptions({
    // BẮT BUỘC: Báo cho Inertia biết trang này KHÔNG sử dụng bất kỳ Layout nào (Kể cả AdminLayout mặc định)
    layout: null,
})

const props = defineProps({
    sale: Object,
})

const formatMoney = (val) => {
    return Number(val || 0).toLocaleString('vi-VN')
}

const saleQuantityText = (item) => {
    const qty = Number(item.quantity ?? 0)
    const unit = item.unit_name || 'Cái'
    return `${Number.isInteger(qty) ? qty : qty.toLocaleString('vi-VN')} ${unit}`
}

onMounted(() => {
    setTimeout(() => {
        window.print()
    }, 300)
})
</script>

<template>
    <div class="receipt-wrapper">
        <div class="receipt-container">
            <!-- Nút bấm thủ công (Tự động ẩn khi in) -->
            <div class="no-print print-bar">
                <span>Xem trước hóa đơn (K80)</span>
                <button type="button" onclick="window.print()" class="btn-print">
                    🖨️ In Hóa Đơn
                </button>
            </div>

            <!-- Header Cửa hàng -->
            <div class="header">
                <h2>ĐỨC HÒA</h2>
                <div class="sub">Điện Thoại - Máy Tính - Camera</div>
                <div>Đ/c: Cầu Giớ - Vạn Xuân - Hưng Yên</div>
                <div class="phone">Hotline: 0906.064.789</div>
            </div>

            <div class="divider"></div>

            <!-- Thông tin đơn hàng -->
            <div class="info">
                <div class="row"><span>Mã HD:</span> <strong>#{{ sale.code }}</strong></div>
                <div class="row"><span>Ngày:</span> <span>{{ sale.created_at }}</span></div>
                <div class="row" v-if="sale.user"><span>Thu ngân:</span> <span>{{ sale.user?.name }}</span></div>
                <div class="row" v-if="sale.customer">
                    <span>Khách hàng:</span>
                    <strong>{{ sale.customer.full_name || sale.customer.name }}</strong>
                </div>
            </div>

            <div class="divider"></div>

            <!-- Danh sách sản phẩm -->
            <div class="items">
                <div v-for="item in sale.items" :key="item.id" class="item">
                    <div class="item-name">
                        {{ item.product?.name || 'Sản phẩm' }}
                        <span v-if="item.variant?.attributes" class="item-variant">
                            ({{ Object.values(item.variant.attributes).filter(Boolean).join(' / ') }})
                        </span>
                    </div>

                    <div v-if="item.product_imei?.imei" class="item-imei">
                        IMEI: {{ item.product_imei.imei }}
                    </div>

                    <div class="item-calc">
                        <span>{{ saleQuantityText(item) }} x {{ formatMoney(item.unit_price) }}</span>
                        <strong>{{ formatMoney(item.subtotal) }}</strong>
                    </div>
                </div>
            </div>

            <div class="divider"></div>

            <!-- Tổng tiền -->
            <div class="totals">
                <div class="row" v-if="sale.subtotal">
                    <span>Tạm tính:</span>
                    <span>{{ formatMoney(sale.subtotal) }} đ</span>
                </div>
                <div class="row total-grand">
                    <span>TỔNG CỘNG:</span>
                    <span>{{ formatMoney(sale.grand_total || sale.total_amount || sale.subtotal) }} đ</span>
                </div>
                <div class="row" v-if="sale.paid_amount">
                    <span>Khách đưa:</span>
                    <span>{{ formatMoney(sale.paid_amount) }} đ</span>
                </div>
                <div class="row" v-if="sale.change_amount">
                    <span>Tiền thừa:</span>
                    <span>{{ formatMoney(sale.change_amount) }} đ</span>
                </div>
            </div>

            <div class="divider"></div>

            <!-- Footer -->
            <div class="footer">
                <div class="thanks">CẢM ƠN QUÝ KHÁCH HÀNG!</div>
                <div class="sub">Hẹn gặp lại quý khách lần sau!</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* CSS Reset cho trang in */
.receipt-wrapper {
    background-color: #f1f5f9;
    min-height: 100vh;
    padding: 20px 0;
    display: flex;
    justify-content: center;
}

.receipt-container {
    width: 80mm;
    background: #fff;
    padding: 12px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    font-size: 11px;
    color: #000;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.print-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #e2e8f0;
    padding: 6px 10px;
    margin-bottom: 12px;
    border-radius: 6px;
}

.btn-print {
    background: #2563eb;
    color: white;
    font-weight: bold;
    padding: 4px 8px;
    border-radius: 4px;
    border: none;
    cursor: pointer;
}

.header { text-align: center; }
.header h2 { font-size: 16px; font-weight: 900; margin: 0; }
.header .sub { font-style: italic; font-size: 10px; }
.header .phone { font-weight: bold; }

.divider {
    border-bottom: 1px dashed #64748b;
    margin: 8px 0;
}

.row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 2px;
}

.item { margin-bottom: 6px; }
.item-name { font-weight: bold; }
.item-variant { font-weight: normal; color: #475569; }
.item-imei { font-family: monospace; font-size: 10px; color: #475569; }
.item-calc { display: flex; justify-content: space-between; margin-top: 2px; }

.total-grand {
    font-size: 13px;
    font-weight: 900;
    margin-top: 4px;
}

.footer { text-align: center; margin-top: 8px; }
.footer .thanks { font-weight: bold; }
.footer .sub { font-size: 10px; font-style: italic; color: #64748b; }

/* 🔴 BẮT BUỘC: KHI NẤM NÚT IN HOẶC TRÌNH DUYỆT TỰ IN -> ẨN SẠCH ADMIN LAYOUT */
@media print {
    /* Ẩn toàn bộ DOM ngoại trừ khung hóa đơn */
    body * {
        visibility: hidden !important;
    }

    .receipt-container,
    .receipt-container * {
        visibility: visible !important;
    }

    .receipt-wrapper {
        padding: 0 !important;
        background: transparent !important;
    }

    .receipt-container {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        box-shadow: none !important;
        padding: 0 !important;
    }

    .no-print {
        display: none !important;
    }

    @page {
        size: 80mm auto;
        margin: 0;
    }
}
</style>