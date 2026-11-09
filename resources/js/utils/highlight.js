// Tô vàng từ khóa: không phân biệt hoa/thường + dấu tiếng Việt, đảo thứ tự từ, gõ sai nhẹ.

const stripChar = (ch) => ch.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/gi, 'd').toLowerCase()

export const normalizeText = (value) => Array.from(String(value ?? '')).map(stripChar).join('')

export const tokenize = (keyword) => [...new Set(
    normalizeText(keyword).split(/[^a-z0-9]+/).filter(Boolean)
)]

const tolerance = (token) => (token.length >= 8 ? 2 : token.length >= 4 ? 1 : 0)

const levenshtein = (a, b) => {
    const prev = Array.from({ length: b.length + 1 }, (_, i) => i)
    for (let i = 1; i <= a.length; i++) {
        let diagonal = prev[0]
        prev[0] = i
        for (let j = 1; j <= b.length; j++) {
            const temp = prev[j]
            prev[j] = Math.min(prev[j] + 1, prev[j - 1] + 1, diagonal + (a[i - 1] === b[j - 1] ? 0 : 1))
            diagonal = temp
        }
    }
    return prev[b.length]
}

/** Trả về [{ text, hit }] để render; hit = true thì bôi vàng. */
export function highlightSegments(text, keyword) {
    const source = String(text ?? '')
    const tokens = tokenize(keyword)
    if (!source || !tokens.length) return [{ text: source, hit: false }]

    // Ánh xạ từng ký tự gốc -> ký tự đã bỏ dấu (giữ đúng vị trí để cắt chuỗi gốc)
    const chars = Array.from(source)
    const folded = chars.map(stripChar)
    const flat = folded.join('')
    const owner = []
    folded.forEach((piece, index) => { for (let i = 0; i < piece.length; i++) owner.push(index) })

    const marked = new Array(chars.length).fill(false)
    const mark = (from, to) => { for (let i = from; i < to; i++) marked[owner[i]] = true }

    for (const token of tokens) {
        let found = false
        let at = flat.indexOf(token)
        while (at !== -1) {
            found = true
            mark(at, at + token.length)
            at = flat.indexOf(token, at + token.length)
        }
        if (found || tolerance(token) === 0) continue

        // Gần đúng: tô cả từ có độ lệch nhỏ
        for (const match of flat.matchAll(/[a-z0-9]+/g)) {
            const word = match[0]
            const distance = Math.min(
                levenshtein(token, word),
                word.length > token.length ? levenshtein(token, word.slice(0, token.length)) : Infinity,
            )
            if (distance <= tolerance(token)) mark(match.index, match.index + word.length)
        }
    }

    const segments = []
    chars.forEach((ch, index) => {
        const last = segments[segments.length - 1]
        if (last && last.hit === marked[index]) last.text += ch
        else segments.push({ text: ch, hit: marked[index] })
    })
    return segments
}
