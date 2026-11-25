import { setModuleArgs } from '@undecaf/zbar-wasm'
import zbarWasmUrl from '@undecaf/zbar-wasm/dist/zbar.wasm?url'

// Cấu hình bộ giải mã ZBar đúng một lần cho cả ứng dụng. Trước đây mỗi lần một trang có nút quét được dựng lên lại gọi
// setModuleArgs, nên sau khi chuyển trang bộ giải mã có thể bị đặt lại giữa lúc đang quét.
let configured = false

export const ensureZbarModule = () => {
    if (configured) return
    configured = true
    setModuleArgs({ locateFile: filename => filename === 'zbar.wasm' ? zbarWasmUrl : filename })
}
