import axios from 'axios'

const api = axios.create({

    headers: {

        Accept: 'application/json',

        'X-Requested-With':
            'XMLHttpRequest',
    },

    withCredentials: true,
})

// thêm interceptor để bắt lỗi 401 Unauthenticated
api.interceptors.response.use(

    (response) => {
        const method = response.config?.method?.toLowerCase()

        if (method && !['get', 'head'].includes(method)) {
            window.dispatchEvent(new Event('reference-data:changed'))

            try {
                window.localStorage.setItem(
                    'reference-data:version',
                    String(Date.now())
                )
            } catch {
                // Không ảnh hưởng yêu cầu vừa hoàn tất.
            }

            if ('BroadcastChannel' in window) {
                const channel = new BroadcastChannel('reference-data')
                channel.postMessage({ type: 'reference-data:changed' })
                channel.close()
            }
        }

        return response
    },

    (error) => {

        if (
            error.response?.status === 401
        ) {

            console.error(
                'Unauthenticated'
            )
        }

        return Promise.reject(error || new Error('Đã có lỗi xảy ra'))
    }
)

export default api
