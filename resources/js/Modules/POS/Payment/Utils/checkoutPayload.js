// Dựng danh sách hàng gửi lên server khi thanh toán.
// Dùng chung cho: thanh toán thường (useCheckout), QR ngân hàng (CheckoutModal)
// và hàng đợi offline (Pages/Pos/Index.vue) để payload luôn đồng nhất.
export const buildCheckoutItems = (cart, { offline = false } = {}) =>
    cart.map(item => ({
        id: item.id,
        price: item.price,
        quantity: item.quantity,
        unit_id: item.unit_id ?? null,
        unit_name: item.unit_name ?? 'Cái',
        conversion_factor: item.conversion_factor ?? 1,
        variant_id: item.variant_id ?? null,
        imei_id: offline ? null : (item.imei_id ?? null),
        apply_vat: Boolean(item.apply_vat),
        warranty_days: item.customer_warranty_days ?? item.warranty_days ?? null,
        warranty_value: item.customer_warranty_value ?? null,
        warranty_unit: item.customer_warranty_unit ?? null,
        warranty_note: item.customer_warranty_note ?? null,
        note: item.note,
        discount_type: item.discount_type ?? null,
        discount_value: Number(item.discount_value ?? 0),
        gifts: (item.gifts ?? []).map(gift => ({
            id: gift.id,
            quantity: gift.quantity,
            variant_id: gift.variant_id ?? null,
            imei_id: offline ? null : (gift.imei_id ?? null),
        })),
    }))
