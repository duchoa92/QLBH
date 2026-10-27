import { BarcodeFormat, QRCodeWriter } from '@zxing/library'
import { VIET_QR_THEMES } from './qrThemes'

const field = (id, value) => {
    const text = String(value ?? '')
    return `${id}${String(text.length).padStart(2, '0')}${text}`
}

const crc16CcittFalse = (text) => {
    let crc = 0xffff

    for (const byte of new TextEncoder().encode(text)) {
        crc ^= byte << 8

        for (let bit = 0; bit < 8; bit += 1) {
            crc = crc & 0x8000 ? (crc << 1) ^ 0x1021 : crc << 1
            crc &= 0xffff
        }
    }

    return crc.toString(16).toUpperCase().padStart(4, '0')
}

const normalizeTransferContent = (value) => String(value ?? '')
    .replace(/đ/gi, match => match === 'đ' ? 'd' : 'D')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-zA-Z0-9 -]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .slice(0, 25)

export function buildVietQrPayload({ bankBin, bankAccount, amount, description }) {
    const bin = String(bankBin ?? '').trim()
    const account = String(bankAccount ?? '').trim()
    const numericAmount = Math.round(Number(amount || 0))

    if (!/^\d{6}$/.test(bin) || !/^[a-zA-Z0-9]{1,50}$/.test(account) || !Number.isFinite(numericAmount) || numericAmount < 0) {
        return ''
    }

    const recipient = field('00', bin) + field('01', account)
    const bankAccountInfo = field('00', 'A000000727')
        + field('01', recipient)
        + field('02', 'QRIBFTTA')
    const transferContent = normalizeTransferContent(description)

    let payload = field('00', '01')
        + field('01', numericAmount > 0 ? '12' : '11')
        + field('38', bankAccountInfo)
        + field('53', '704')

    if (numericAmount > 0) {
        payload += field('54', numericAmount)
    }

    payload += field('58', 'VN')

    if (transferContent) {
        payload += field('62', field('08', transferContent))
    }

    payload += '6304'

    return payload + crc16CcittFalse(payload)
}
export function renderVietQrImage(payload, themeName = 'classic') {
    if (!payload) return ''

    try {
        const theme = VIET_QR_THEMES.find(item => item.value === themeName) || VIET_QR_THEMES[0]
        const matrix = new QRCodeWriter().encode(
            payload,
            BarcodeFormat.QR_CODE,
            320,
            320,
            new Map(),
        )
        const width = matrix.getWidth()
        const height = matrix.getHeight()
        let path = ''

        for (let y = 0; y < height; y += 1) {
            for (let x = 0; x < width; x += 1) {
                if (matrix.get(x, y)) path += `M${x} ${y}h1v1h-1z`
            }
        }

        const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width} ${height}" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="${theme.background}"/><path d="${path}" fill="${theme.foreground}"/></svg>`
        return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`
    } catch (error) {
        console.error('Không thể tạo mã QR chuyển khoản', error)
        return ''
    }
}
