import { describe, it, expect } from 'vitest'
import axios, { AxiosError } from 'axios'
import { parseApiError, extractData } from '@/lib/api'

// ── Helper: create a mock AxiosError ─────────────────────────────────

function makeAxiosError(responseData: unknown, status = 422): AxiosError {
  const error = new AxiosError(
    'Request failed',
    'ERR_BAD_REQUEST',
    undefined,
    undefined,
    {
      data: responseData,
      status,
      statusText: 'Unprocessable Entity',
      headers: {},
      config: {} as any,
    } as any,
  )
  return error
}

// ── parseApiError ────────────────────────────────────────────────────

describe('parseApiError', () => {
  it('returns error message from JSON:API errors array', () => {
    const error = makeAxiosError({
      errors: [
        { status: '422', title: 'Validation Error', detail: 'Name is required' },
        { status: '422', title: 'Validation Error', detail: 'Email is invalid' },
      ],
    })
    expect(parseApiError(error)).toBe('Name is required; Email is invalid')
  })

  it('handles single error in array', () => {
    const error = makeAxiosError({
      errors: [
        { status: '404', title: 'Not Found', detail: 'Resource not found' },
      ],
    })
    expect(parseApiError(error)).toBe('Resource not found')
  })

  it('handles missing detail, falls back to title', () => {
    const error = makeAxiosError({
      errors: [
        { status: '500', title: 'Internal Server Error' },
      ],
    })
    expect(parseApiError(error)).toBe('Internal Server Error')
  })

  it('handles non-JSON:API error responses (legacy format)', () => {
    const error = makeAxiosError({
      message: 'Something went wrong',
    })
    expect(parseApiError(error)).toBe('Something went wrong')
  })

  it('returns generic message for unknown AxiosError without body', () => {
    const error = new AxiosError('Network Error')
    expect(parseApiError(error)).toBe('Network Error')
  })

  it('returns message from plain Error', () => {
    const error = new Error('Plain JS error')
    expect(parseApiError(error)).toBe('Plain JS error')
  })

  it('returns fallback string for non-error values', () => {
    expect(parseApiError('some string')).toBe('An unexpected error occurred')
    expect(parseApiError(null)).toBe('An unexpected error occurred')
    expect(parseApiError(undefined)).toBe('An unexpected error occurred')
  })
})

// ── extractData ──────────────────────────────────────────────────────

describe('extractData', () => {
  it('extracts data array from { data: [...] } envelope', () => {
    const response = { data: [{ id: 1, name: 'Test' }] }
    expect(extractData(response)).toEqual([{ id: 1, name: 'Test' }])
  })

  it('extracts single object from { data: {...} } envelope', () => {
    const response = { data: { id: 1, name: 'Test' } }
    expect(extractData(response)).toEqual({ id: 1, name: 'Test' })
  })

  it('passes through plain arrays', () => {
    const data = [{ id: 1 }, { id: 2 }]
    expect(extractData(data)).toEqual(data)
  })

  it('passes through plain objects without data key', () => {
    const data = { id: 1, name: 'Test' }
    expect(extractData(data)).toEqual(data)
  })

  it('handles null/undefined gracefully', () => {
    expect(extractData(null)).toBeNull()
    expect(extractData(undefined)).toBeUndefined()
  })
})
