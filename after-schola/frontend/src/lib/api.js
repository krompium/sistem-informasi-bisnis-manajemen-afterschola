import axios from 'axios'

const STORAGE_TOKEN_KEY = 'as_token'

// Instance axios terpusat untuk semua panggilan API.
// Auth memakai token Sanctum (Bearer) — sesuai keputusan API-first di PRD
// agar endpoint yang sama bisa dipakai ulang oleh mobile app.
const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

// Sisipkan token pada setiap request bila ada.
api.interceptors.request.use((config) => {
  const token = localStorage.getItem(STORAGE_TOKEN_KEY)
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Token gagal / kadaluarsa -> bersihkan & arahkan ke login.
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      localStorage.removeItem(STORAGE_TOKEN_KEY)
      if (window.location.pathname !== '/login') {
        window.location.assign('/login')
      }
    }
    return Promise.reject(error)
  },
)

export { STORAGE_TOKEN_KEY }
export default api
