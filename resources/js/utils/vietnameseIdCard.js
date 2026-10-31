const clean = value => String(value || '').trim()

const parseDate = value => {
    const digits = clean(value).replace(/\D/g, '')
    if (digits.length !== 8) return ''
    const day = digits.slice(0, 2)
    const month = digits.slice(2, 4)
    const year = digits.slice(4)
    return `${year}-${month}-${day}`
}

export function parseVietnameseIdCardQr(raw) {
    const value = clean(raw)
    if (!value) return null

    const fields = value.split('|').map(clean)
    const id = fields[0]?.replace(/\D/g, '') || ''
    if (!/^\d{9,12}$/.test(id) || fields.length < 6) return null

    const genderText = fields[4].toLocaleLowerCase('vi')
    const addressParts = (fields[5] || '').split(',').map(clean).filter(Boolean)
    const ward = addressParts.find(part => /\b(xã|phường|thị trấn)\b/i.test(part)) || ''
    const district = addressParts.find(part => /\b(quận|huyện|thị xã|thành phố)\b/i.test(part) && part !== ward) || ''
    const province = [...addressParts].reverse().find(part => /\b(tỉnh|thành phố)\b/i.test(part) && part !== district) || ''
    const streetAddress = addressParts.filter(part => part !== ward && part !== district && part !== province).join(', ')

    return {
        cccd: id,
        full_name: fields[2] || '',
        birthday: parseDate(fields[3]),
        gender: genderText === 'nam' ? 'male' : genderText === 'nữ' || genderText === 'nu' ? 'female' : '',
        address: fields[5] || '',
        ward,
        district,
        province,
        street_address: streetAddress,
    }
}
