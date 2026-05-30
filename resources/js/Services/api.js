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

    (response) => response,

    (error) => {

        if (
            error.response?.status === 401
        ) {

            console.error(
                'Unauthenticated'
            )
        }

        return Promise.reject(error)
    }
)

export default api