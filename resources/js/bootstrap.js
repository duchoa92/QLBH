import axios from 'axios'
import { appBaseUrl } from '@/utils/appPaths'

window.axios = axios
window.axios.defaults.baseURL = appBaseUrl

window.axios.defaults.headers.common['X-Requested-With']
    = 'XMLHttpRequest'

window.axios.defaults.withCredentials = true

window.axios.defaults.withXSRFToken = true
