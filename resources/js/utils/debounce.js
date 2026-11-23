// debounce nhỏ gọn (có .cancel()) thay cho lodash/debounce, dùng cho ô tìm kiếm.
export default function debounce(fn, wait = 300) {
    let timer = null
    let lastArgs = null

    const debounced = (...args) => {
        lastArgs = args
        clearTimeout(timer)
        timer = setTimeout(() => {
            timer = null
            const callArgs = lastArgs
            lastArgs = null
            fn(...callArgs)
        }, wait)
    }

    debounced.cancel = () => {
        clearTimeout(timer)
        timer = null
        lastArgs = null
    }

    return debounced
}
