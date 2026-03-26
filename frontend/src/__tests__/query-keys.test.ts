import { describe, it, expect } from 'vitest'
import { queryKeys } from '@/lib/query-keys'

// ── Key factory basics ──────────────────────────────────────────────

describe('queryKeys', () => {
  describe('all key factories return arrays', () => {
    it('projects.all is an array', () => {
      expect(Array.isArray(queryKeys.projects.all)).toBe(true)
    })

    it('clusters.all is an array', () => {
      expect(Array.isArray(queryKeys.clusters.all)).toBe(true)
    })

    it('accounts.all is an array', () => {
      expect(Array.isArray(queryKeys.accounts.all)).toBe(true)
    })

    it('yandex.status() is an array', () => {
      expect(Array.isArray(queryKeys.yandex.status())).toBe(true)
    })

    it('positionMatrix.list() is an array', () => {
      expect(Array.isArray(queryKeys.positionMatrix.list('1', 14))).toBe(true)
    })
  })

  // ── clusters ────────────────────────────────────────────────────────

  describe('clusters', () => {
    it('clusters.all returns ["clusters"]', () => {
      expect(queryKeys.clusters.all).toEqual(['clusters'])
    })

    it('clusters.byProject(id) starts with ["clusters", ...] (not "project-clusters")', () => {
      const key = queryKeys.clusters.byProject('42')
      expect(key[0]).toBe('clusters')
      expect(key[0]).not.toBe('project-clusters')
    })

    it('clusters.byProject(id) is a superset of clusters.all (for cache invalidation)', () => {
      const allKey = queryKeys.clusters.all
      const byProjectKey = queryKeys.clusters.byProject('42')

      // byProject key must start with the same prefix as all
      expect(byProjectKey.slice(0, allKey.length)).toEqual([...allKey])
      expect(byProjectKey.length).toBeGreaterThan(allKey.length)
    })
  })

  // ── positionMatrix ──────────────────────────────────────────────────

  describe('positionMatrix', () => {
    it('list(projectId, days) includes both params', () => {
      const key = queryKeys.positionMatrix.list('5', 14)
      expect(key).toContain('5')
      expect(key).toContain(14)
    })

    it('list with different days produces different keys', () => {
      const key7 = queryKeys.positionMatrix.list('5', 7)
      const key14 = queryKeys.positionMatrix.list('5', 14)
      expect(key7).not.toEqual(key14)
    })
  })

  // ── yandex ──────────────────────────────────────────────────────────

  describe('yandex', () => {
    it('yandex.status() is a superset of yandex.all', () => {
      const allKey = queryKeys.yandex.all
      const statusKey = queryKeys.yandex.status()

      expect(statusKey.slice(0, allKey.length)).toEqual([...allKey])
      expect(statusKey.length).toBeGreaterThan(allKey.length)
    })
  })

  // ── accounts ────────────────────────────────────────────────────────

  describe('accounts', () => {
    it('accounts.all returns ["accounts"]', () => {
      expect(queryKeys.accounts.all).toEqual(['accounts'])
    })
  })

  // ── referential consistency ─────────────────────────────────────────

  describe('referential consistency', () => {
    it('key factories with same inputs return identical arrays', () => {
      expect(queryKeys.clusters.byProject('10')).toEqual(
        queryKeys.clusters.byProject('10'),
      )
      expect(queryKeys.positionMatrix.list('5', 14)).toEqual(
        queryKeys.positionMatrix.list('5', 14),
      )
      expect(queryKeys.yandex.status()).toEqual(queryKeys.yandex.status())
      expect(queryKeys.keywords.detail('1', '2')).toEqual(
        queryKeys.keywords.detail('1', '2'),
      )
    })

    it('static keys are referentially stable (same object)', () => {
      expect(queryKeys.clusters.all).toBe(queryKeys.clusters.all)
      expect(queryKeys.accounts.all).toBe(queryKeys.accounts.all)
      expect(queryKeys.projects.all).toBe(queryKeys.projects.all)
    })
  })
})
