export const imageUrl = (value) => {
    if (!value) return null

    const path = typeof value === 'object'
        ? (value.url ?? value.path ?? value.image_url ?? null)
        : value

    if (!path) return null

    const normalized = String(path)

    if (/^(https?:|data:|blob:)/i.test(normalized)) {
        return normalized
    }

    return `/storage/${normalized.replace(/^\/?(?:storage\/)?/, '')}`
}
