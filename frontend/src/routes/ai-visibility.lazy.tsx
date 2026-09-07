import { createLazyFileRoute } from '@tanstack/react-router'
import { useState } from 'react'
import { AppLayout } from '@/components/AppLayout'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useBrandLookup } from '@/hooks/useAiVisibility'
import { parseApiError } from '@/lib/api'
import { Sparkles } from 'lucide-react'

export const Route = createLazyFileRoute('/ai-visibility')({
  component: AiVisibilityPage,
})

/** Вопросы, которые задал бы покупатель — с них удобно начинать. */
const EXAMPLE_PROMPTS = 'какие летние шины выбрать для кроссовера\nлучшие российские производители шин\nгде купить шины с доставкой'

function AiVisibilityPage() {
  const [brand, setBrand] = useState('')
  const [domain, setDomain] = useState('')
  const [competitors, setCompetitors] = useState('')
  const [prompts, setPrompts] = useState(EXAMPLE_PROMPTS)
  const lookup = useBrandLookup()

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    lookup.mutate({
      brand: brand.trim(),
      domain: domain.trim(),
      competitors: competitors.split(',').map((s) => s.trim()).filter(Boolean),
      prompts: prompts.split('\n').map((s) => s.trim()).filter(Boolean).slice(0, 10),
    })
  }

  const result = lookup.data
  const error = lookup.error ? parseApiError(lookup.error) : null

  return (
    <AppLayout>
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-semibold flex items-center gap-2">
            <Sparkles className="h-6 w-6" /> AI-видимость
          </h1>
          <p className="text-muted-foreground mt-1">
            Как часто ИИ-ассистент называет ваш бренд в ответах на вопросы покупателей и ссылается ли
            на ваш сайт. Считается доля голоса против конкурентов.
          </p>
        </div>

        <Card>
          <CardContent className="pt-6">
            <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
              <div className="space-y-1">
                <Label htmlFor="brand">Бренд</Label>
                <Input id="brand" value={brand} onChange={(e) => setBrand(e.target.value)} placeholder="Cordiant" required />
              </div>
              <div className="space-y-1">
                <Label htmlFor="domain">Домен</Label>
                <Input id="domain" value={domain} onChange={(e) => setDomain(e.target.value)} placeholder="cordiant.ru" required />
              </div>
              <div className="space-y-1 md:col-span-2">
                <Label htmlFor="competitors">Конкуренты через запятую</Label>
                <Input id="competitors" value={competitors} onChange={(e) => setCompetitors(e.target.value)} placeholder="Nokian, Viatti, Kama" />
              </div>
              <div className="space-y-1 md:col-span-2">
                <Label htmlFor="prompts">Вопросы покупателя — по одному на строку, до 10</Label>
                <textarea
                  id="prompts"
                  className="w-full min-h-28 rounded-md border bg-background px-3 py-2 text-sm"
                  value={prompts}
                  onChange={(e) => setPrompts(e.target.value)}
                />
              </div>
              <div className="md:col-span-2">
                <Button type="submit" disabled={lookup.isPending}>
                  {lookup.isPending ? 'Спрашиваем модель…' : 'Проверить'}
                </Button>
                {error && <p className="mt-2 text-sm text-red-600">{error}</p>}
              </div>
            </form>
          </CardContent>
        </Card>

        {result && (
          <>
            <div className="grid gap-4 md:grid-cols-3">
              <Card>
                <CardHeader><CardTitle className="text-base">Доля голоса</CardTitle></CardHeader>
                <CardContent>
                  <Table>
                    <TableBody>
                      {Object.entries(result.share_of_voice).map(([name, row]) => (
                        <TableRow key={name}>
                          <TableCell className="font-medium">{name}</TableCell>
                          <TableCell className="text-right">{row.mentions}</TableCell>
                          <TableCell className="text-right text-muted-foreground">
                            {row.share === null ? '—' : `${row.share}%`}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
              <Card>
                <CardHeader><CardTitle className="text-base">Ссылки на ваш сайт</CardTitle></CardHeader>
                <CardContent>
                  <div className="text-3xl font-semibold">{result.our_citations}</div>
                  <p className="text-sm text-muted-foreground">из {result.prompts} ответов · модель {result.model}</p>
                  {result.unanswered > 0 && (
                    <p className="text-sm text-amber-600 mt-1">Без ответа: {result.unanswered}</p>
                  )}
                </CardContent>
              </Card>
              <Card>
                <CardHeader><CardTitle className="text-base">Кого цитирует модель</CardTitle></CardHeader>
                <CardContent>
                  <Table>
                    <TableBody>
                      {Object.entries(result.cited_hosts).slice(0, 8).map(([host, n]) => (
                        <TableRow key={host}>
                          <TableCell className="font-mono text-xs">{host}</TableCell>
                          <TableCell className="text-right">{n}</TableCell>
                        </TableRow>
                      ))}
                      {Object.keys(result.cited_hosts).length === 0 && (
                        <TableRow><TableCell className="text-muted-foreground">Ссылок в ответах нет</TableCell></TableRow>
                      )}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            </div>

            <Card>
              <CardHeader><CardTitle className="text-base">Ответы по вопросам</CardTitle></CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Вопрос</TableHead>
                      <TableHead>Упомянуты</TableHead>
                      <TableHead>Наш сайт</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {result.answers.map((a, i) => (
                      <TableRow key={i}>
                        <TableCell>
                          <div className="font-medium">{a.prompt}</div>
                          {a.answer && (
                            <details className="mt-1 text-xs text-muted-foreground">
                              <summary className="cursor-pointer">ответ модели</summary>
                              <div className="whitespace-pre-wrap mt-1">{a.answer}</div>
                            </details>
                          )}
                        </TableCell>
                        <TableCell className="space-x-1">
                          {a.mentions.map((m) => <Badge key={m} variant="secondary">{m}</Badge>)}
                          {a.mentions.length === 0 && <span className="text-muted-foreground">—</span>}
                        </TableCell>
                        <TableCell>{a.cites_us ? <Badge>цитирует</Badge> : <span className="text-muted-foreground">нет</span>}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </>
        )}
      </div>
    </AppLayout>
  )
}
