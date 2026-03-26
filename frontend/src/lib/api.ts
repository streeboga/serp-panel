import axios, { type AxiosError } from 'axios'

/**
 * JSON:API error format
 */
export interface ApiError {
  status: string
  title: string
  detail?: string
  source?: { pointer?: string; parameter?: string }
}

export interface ApiErrorResponse {
  errors: ApiError[]
}

/**
 * Parse JSON:API error response into a user-friendly message
 */
export function parseApiError(error: unknown): string {
  if (!axios.isAxiosError(error)) {
    return error instanceof Error ? error.message : 'An unexpected error occurred'
  }

  const axiosError = error as AxiosError<ApiErrorResponse>
  const data = axiosError.response?.data

  // JSON:API error format
  if (data && 'errors' in data && Array.isArray(data.errors) && data.errors.length > 0) {
    return data.errors.map((e) => e.detail ?? e.title).join('; ')
  }

  // Legacy format
  if (data && typeof data === 'object' && 'message' in data) {
    return (data as { message: string }).message
  }

  return axiosError.message ?? 'Request failed'
}

/**
 * Extract data from JSON:API response.
 * Handles both `{ data: ... }` envelope and flat responses.
 */
export function extractData<T>(response: unknown): T {
  if (response && typeof response === 'object' && 'data' in response) {
    return (response as { data: T }).data
  }
  return response as T
}

const api = axios.create({
  baseURL: '/api/v1',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  const orgId = localStorage.getItem('organization_id')

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  if (orgId) {
    config.headers['X-Organization-Id'] = orgId
  }

  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (axios.isAxiosError(error) && error.response?.status === 401) {
      localStorage.removeItem('token')
      localStorage.removeItem('organization_id')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  },
)

export default api
