// Dịch lỗi từ Google Drive/OneDrive sang câu dễ hiểu cho người dùng.
export const formatCloudError = (error, provider = 'google_drive') => {
    const raw = String(error || '').trim()
    const message = raw.toLowerCase()
    const service = provider === 'onedrive' ? 'OneDrive' : 'Google Drive'
    const status = Number(raw.match(/(?:HTTP|status code)\s*[:=]?\s*(\d{3})/i)?.[1] || 0)

    if (/project.{0,80}(has been deleted|deleted)|has been deleted.{0,80}project/.test(message)) {
        return `Dự án Google Cloud đang cấp quyền cho kết nối này đã bị xóa. Hãy chọn hoặc tạo dự án còn hoạt động, bật Google Drive API, cập nhật OAuth Client ID/Secret trên máy chủ, rồi xóa kết nối cũ và kết nối lại.`
    }
    if (/storagequotaexceeded|quota.{0,30}(exceed|full)|storage quota/.test(message)) {
        return 'Google Drive đã hết dung lượng. Hãy giải phóng dung lượng hoặc kết nối tài khoản Drive khác.'
    }
    if (/redirect_uri_mismatch/.test(message)) {
        return 'Địa chỉ chuyển hướng OAuth chưa khớp. Hãy khai báo đúng Redirect URI trong ứng dụng OAuth.'
    }
    if (/invalid_client|unauthorized_client/.test(message)) {
        return 'Thông tin OAuth của ứng dụng không hợp lệ. Hãy kiểm tra Client ID và Client Secret trên máy chủ.'
    }
    if (/invalid_grant|token has been expired|refresh token|interaction_required/.test(message)) {
        return `Quyền truy cập ${service} đã hết hạn hoặc bị thu hồi. Hãy xóa kết nối cũ rồi kết nối lại.`
    }
    if (/access_denied|user denied|consent_required/.test(message)) {
        return `Bạn chưa cấp quyền cho ứng dụng truy cập ${service}. Hãy kết nối lại và chấp thuận các quyền được yêu cầu.`
    }
    if (/insufficientpermissions|insufficient permission|accessdenied|forbidden/.test(message) || status === 403) {
        return `Ứng dụng chưa được cấp đủ quyền với ${service}. Hãy kiểm tra quyền OAuth và quyền truy cập kho, sau đó kết nối lại.`
    }
    if (/invalid credentials|invalidauthenticationtoken|unauthenticated/.test(message) || status === 401) {
        return `Phiên kết nối ${service} không còn hợp lệ. Hãy xóa kết nối cũ rồi đăng nhập và kết nối lại.`
    }
    if (/rate limit|ratelimit|userratelimitexceeded/.test(message) || status === 429) {
        return `${service} đang giới hạn số yêu cầu. Hãy chờ ít phút rồi thử lại.`
    }
    if (/timed out|timeout|could not resolve|connection refused|network error/.test(message)) {
        return `Không thể liên lạc với ${service}. Kiểm tra kết nối mạng rồi thử lại.`
    }
    if (status >= 500) return `${service} đang gặp sự cố tạm thời. Hãy thử lại sau.`
    return `Không thể truy cập ${service}. Hãy kiểm tra quyền của ứng dụng và thử kết nối lại.`
}
