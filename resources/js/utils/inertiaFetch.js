// Lấy đúng dữ liệu mà trang đích sẽ nhận (cùng controller, cùng quan hệ eager-load)
// bằng "partial reload" của Inertia, nhưng KHÔNG điều hướng khỏi trang hiện tại.
export async function fetchPageProps(url, component, only, version = '') {
    const { data } = await window.axios.get(url, {
        headers: {
            Accept: 'text/html, application/xhtml+xml',
            'X-Requested-With': 'XMLHttpRequest',
            'X-Inertia': 'true',
            'X-Inertia-Version': version || '',
            'X-Inertia-Partial-Component': component,
            'X-Inertia-Partial-Data': only.join(','),
        },
    })

    if (!data?.props) throw new Error('Phản hồi không hợp lệ')
    return data.props
}
