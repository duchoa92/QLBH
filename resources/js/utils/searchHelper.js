// Chuẩn hóa xóa dấu tiếng Việt
export const removeVietnameseTones = (str) => {
    if (!str) return ''
    return str
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'd')
        .trim()
}

// Logic lọc dữ liệu gần đúng theo chuỗi từ khóa
export const filterByKeywords = (list, searchInput, getSearchableTextFn) => {
    if (!searchInput || !searchInput.trim()) return list

    const searchNormalized = removeVietnameseTones(searchInput)
    const keywords = searchNormalized.split(/\s+/).filter(Boolean)

    return list.filter(item => {
        const itemText = getSearchableTextFn ? getSearchableTextFn(item) : String(item)
        const labelNormalized = removeVietnameseTones(itemText)
        return keywords.every(kw => labelNormalized.includes(kw))
    })
}

// Map các ký tự không dấu sang Regex chứa tất cả biến thể có dấu của nó
const mapCharToVietnameseRegex = (char) => {
    const map = {
        'a': '[aàáảãạâầấẩẫậăằắẳẵặAÀÁẢÃẠÂẦẤẨẪẬĂẰẮẲẴẶ]',
        'e': '[eèéẻẽẹêềếểễệEÈÉẺẼẸÊỀẾỂỄỆ]',
        'i': '[iìíỉĩịIÌÍỈĨỊ]',
        'o': '[oòóỏõọôồốổỗộơờớởỡợOÒÓỎÕỌÔỒỐỔỖỘƠỜỚỞỠỢ]',
        'u': '[uùúủũụưừứửữựUÙÚỦŨỤƯỪỨỬỮỰ]',
        'y': '[yỳýỷỹỵYỲÝỶỸỴ]',
        'd': '[dđĐD]'
    }
    return map[char.toLowerCase()] || char.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

// Chuyển 1 từ khóa không dấu thành Pattern Regex chuẩn tiếng Việt
const makeVietnameseRegexPattern = (keyword) => {
    return keyword
        .split('')
        .map(char => mapCharToVietnameseRegex(char))
        .join('')
}

// Hàm tô đậm (Highlight) chính xác từng vị trí chữ có dấu
export const highlightText = (text, searchInput) => {
    if (!text) return ''
    if (!searchInput || !searchInput.trim()) return text

    const searchNormalized = removeVietnameseTones(searchInput)
    const keywords = searchNormalized.split(/\s+/).filter(Boolean)

    if (keywords.length === 0) return text

    // Tạo pattern tổng hợp chứa tất cả từ khóa người dùng gõ
    const patterns = keywords.map(kw => makeVietnameseRegexPattern(kw))
    const regex = new RegExp(`(${patterns.join('|')})`, 'gi')

    // Thay thế trực tiếp từ trùng khớp bằng thẻ mark
    return String(text).replace(regex, '<mark class="bg-yellow-200 text-yellow-900 font-bold rounded px-0.5">$1</mark>')
}