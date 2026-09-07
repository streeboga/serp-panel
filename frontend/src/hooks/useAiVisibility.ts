import { useMutation } from '@tanstack/react-query'
import api from '@/lib/api'

export interface BrandLookupInput {
  brand: string
  domain: string
  competitors: string[]
  prompts: string[]
}

export interface BrandLookupResult {
  model: string
  prompts: number
  unanswered: number
  share_of_voice: Record<string, { mentions: number; share: number | null }>
  our_citations: number
  cited_hosts: Record<string, number>
  answers: { prompt: string; answer: string | null; mentions: string[]; cites_us: boolean }[]
}

export function useBrandLookup() {
  return useMutation({
    mutationFn: (input: BrandLookupInput) =>
      api.post('/ai-visibility/lookup', input).then((r) => r.data.data as BrandLookupResult),
  })
}
