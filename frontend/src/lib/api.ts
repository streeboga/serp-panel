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

/**
 * Flatten a single JSON:API resource { type, id, attributes, relationships }
 * into a plain object { id, ...attributes, ...flattenedRelationships }
 */
function flattenResource(resource: Record<string, unknown>): Record<string, unknown> {
  if (!resource || typeof resource !== 'object') return resource
  if (!('attributes' in resource)) return resource

  const flat: Record<string, unknown> = {
    id: Number(resource.id),
    ...(resource.attributes as Record<string, unknown>),
  }

  if (resource.relationships && typeof resource.relationships === 'object') {
    if (Array.isArray(resource.relationships)) {
      // empty relationships array — skip
    } else {
      for (const [key, value] of Object.entries(resource.relationships as Record<string, unknown>)) {
        if (Array.isArray(value)) {
          flat[key] = value.map((v) =>
            v && typeof v === 'object' && 'attributes' in v ? flattenResource(v as Record<string, unknown>) : v,
          )
        } else if (value && typeof value === 'object') {
          const rel = value as Record<string, unknown>
          if ('data' in rel) {
            const d = rel.data
            if (Array.isArray(d)) {
              flat[key] = d.map((v: Record<string, unknown>) => flattenResource(v))
            } else if (d && typeof d === 'object') {
              flat[key] = flattenResource(d as Record<string, unknown>)
            } else {
              flat[key] = d
            }
          } else if ('attributes' in rel) {
            flat[key] = flattenResource(rel)
          }
        }
      }
    }
  }

  return flat
}

/**
 * Response interceptor: auto-flatten JSON:API responses so all hooks
 * and components receive plain objects instead of { type, id, attributes } wrappers.
 */
api.interceptors.response.use(
  (response) => {
    const d = response.data

    if (d && typeof d === 'object' && 'data' in d) {
      const payload = d.data

      if (Array.isArray(payload)) {
        // Collection: check if items have JSON:API shape
        if (payload.length > 0 && payload[0]?.attributes) {
          response.data = {
            ...d,
            data: payload.map((item: Record<string, unknown>) => flattenResource(item)),
          }
        }
      } else if (payload && typeof payload === 'object' && 'attributes' in payload) {
        // Single resource
        response.data = {
          ...d,
          data: flattenResource(payload as Record<string, unknown>),
        }
      }
    }

    return response
  },
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
