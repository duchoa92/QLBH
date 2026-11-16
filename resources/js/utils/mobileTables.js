// Mobile: bảng có class "m-cards" được CSS (app.css) chuyển thành danh sách thẻ.
// Hàm này chỉ gắn nhãn cột (data-label) cho từng ô dựa vào tiêu đề cột để CSS hiển thị "Nhãn: giá trị".
// Không đổi cấu trúc DOM, không ảnh hưởng dữ liệu hay sự kiện; trên desktop bảng giữ nguyên.

const ACTION_HEADER = /^(thao\s*t[aá]c|actions?|)$/i

const labelTable = (table) => {
    const headRows = table.tHead ? Array.from(table.tHead.rows) : []
    const headerRow = headRows.length ? headRows[headRows.length - 1] : null
    const labels = []

    if (headerRow) {
        Array.from(headerRow.cells).forEach((cell) => {
            const span = Math.max(1, cell.colSpan || 1)
            const text = (cell.textContent || '').replace(/\s+/g, ' ').trim()
            for (let i = 0; i < span; i += 1) labels.push(text)
        })
    }

    Array.from(table.tBodies).concat(table.tFoot ? [table.tFoot] : []).forEach((section) => {
        Array.from(section.rows).forEach((row) => {
            let col = 0
            Array.from(row.cells).forEach((cell) => {
                const span = Math.max(1, cell.colSpan || 1)
                const label = span > 1 ? '' : (labels[col] || '')
                if (cell.dataset.label !== label) cell.dataset.label = label
                const isAction = !!headerRow && span === 1 && ACTION_HEADER.test(labels[col] ?? 'x') && cell.querySelector('button, a')
                cell.classList.toggle('m-cell-actions', Boolean(isAction))
                // data-m-wide: ô chiếm cả hàng, không nhãn; data-m-hide: ẩn ô trên thẻ mobile (desktop vẫn hiện).
                cell.classList.toggle('m-cell-wide', span > 1 || !label || cell.dataset.mWide !== undefined)
                cell.classList.toggle('m-cell-hide', cell.dataset.mHide !== undefined)
                col += span
            })
        })
    })
}

export function installMobileTables() {
    if (typeof document === 'undefined' || typeof MutationObserver === 'undefined') return

    let scheduled = false
    const run = () => {
        scheduled = false
        document.querySelectorAll('table.m-cards').forEach(labelTable)
    }
    const schedule = () => {
        if (scheduled) return
        scheduled = true
        window.requestAnimationFrame(run)
    }

    const start = () => {
        new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true, characterData: true })
        schedule()
    }

    if (document.body) start()
    else document.addEventListener('DOMContentLoaded', start)
}
