import { createLazyFileRoute } from '@tanstack/react-router'
import { useState } from 'react'
import { AppLayout } from '@/components/AppLayout'
import { TableSkeleton } from '@/components/PageSkeleton'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useYandexStatus, useYandexRedirect } from '@/hooks/useYandex'
import {
  useIntegrationSites,
  useGoogleStatus,
  useGoogleRedirect,
  useGoogleDisconnect,
  useMapDomain,
} from '@/hooks/useIntegrations'
import { Link2, ExternalLink, Wand2 } from 'lucide-react'

export const Route = createLazyFileRoute('/settings/integrations')({
  component: IntegrationsPage,
})

/** Значение для «ничего не выбрано»: пустая строка селекту не годится. */
const NONE = '__none__'

function IntegrationsPage() {
  const { data, isLoading } = useIntegrationSites()
  const yandex = useYandexStatus()
  const google = useGoogleStatus()
  const yandexRedirect = useYandexRedirect()
  const googleRedirect = useGoogleRedirect()
  const googleDisconnect = useGoogleDisconnect()
  const mapDomain = useMapDomain()
  const [counters, setCounters] = useState<Record<string, string>>({})

  const connect = async (which: 'yandex' | 'google') => {
    const result =
      which === 'yandex' ? await yandexRedirect.mutateAsync() : await googleRedirect.mutateAsync()
    if (result?.url) window.location.href = result.url
  }

  const webmasterSites = data?.webmaster.sites ?? []
  const consoleSites = data?.search_console.sites ?? []

  const applySuggestions = () => {
    for (const domain of data?.domains ?? []) {
      const patch: Record<string, string> = {}
      if (!domain.webmaster_host_id && domain.suggested.webmaster_host_id) {
        patch.webmaster_host_id = domain.suggested.webmaster_host_id
      }
      if (!domain.search_console_site && domain.suggested.search_console_site) {
        patch.search_console_site = domain.suggested.search_console_site
      }
      if (Object.keys(patch).length > 0) mapDomain.mutate({ id: domain.id, ...patch })
    }
  }

  const hasSuggestions = (data?.domains ?? []).some(
    (d) =>
      (!d.webmaster_host_id && d.suggested.webmaster_host_id) ||
      (!d.search_console_site && d.suggested.search_console_site),
  )

  return (
    <AppLayout>
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-semibold flex items-center gap-2">
            <Link2 className="h-6 w-6" /> Интеграции
          </h1>
          <p className="text-muted-foreground mt-1">
            Вебмастер, Search Console и Метрика отдают данные только владельцу сайта. Подключите
            аккаунт и сопоставьте его ресурсы с вашими доменами.
          </p>
        </div>

        <div className="grid gap-4 md:grid-cols-2">
          <ConnectionCard
            title="Яндекс"
            hint="Вебмастер и Метрика. Приложению нужно право webmaster:hostinfo."
            connected={!!yandex.data?.connected}
            reason={data?.webmaster.reason}
            onConnect={() => connect('yandex')}
          />
          <ConnectionCard
            title="Google"
            hint="Search Console: запросы, клики, показы и позиции."
            connected={!!google.data?.connected}
            configured={google.data?.configured !== false}
            reason={data?.search_console.reason}
            onConnect={() => connect('google')}
            onDisconnect={() => googleDisconnect.mutate()}
          />
        </div>

        <Card>
          <CardHeader>
            <CardTitle>MCP для агентов</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2 text-sm">
            <p className="text-muted-foreground">
              Claude Code, Cursor и другие агенты могут читать позиции, ключи, конкурентов и запускать
              аудит напрямую. Токен — тот же, что для API (Настройки → API-токены).
            </p>
            <pre className="rounded-md bg-muted p-3 text-xs overflow-x-auto">{`claude mcp add serp-panel ${window.location.origin.replace(/^https?:\/\//, 'https://api-')}/mcp \\
  --transport http --header "Authorization: Bearer <token>"`}</pre>
            <p className="text-muted-foreground">
              Инструменты: whoami, list_projects, get_positions, list_keywords, get_keyword_frequency,
              get_competitors, get_dashboard, run_site_audit, get_audit, get_audit_issues, check_url.
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>Сопоставление доменов</CardTitle>
            {hasSuggestions && (
              <Button variant="outline" size="sm" onClick={applySuggestions}>
                <Wand2 className="h-4 w-4 mr-2" /> Подставить совпадения
              </Button>
            )}
          </CardHeader>
          <CardContent>
            {isLoading ? (
              <TableSkeleton />
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Домен</TableHead>
                    <TableHead>Проект</TableHead>
                    <TableHead>Яндекс.Вебмастер</TableHead>
                    <TableHead>Search Console</TableHead>
                    <TableHead>Счётчик Метрики</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {(data?.domains ?? []).map((domain) => (
                    <TableRow key={domain.id}>
                      <TableCell className="font-medium">{domain.name}</TableCell>
                      <TableCell className="text-muted-foreground">
                        {domain.project_name}
                      </TableCell>
                      <TableCell>
                        <ResourceSelect
                          value={domain.webmaster_host_id}
                          suggested={domain.suggested.webmaster_host_id}
                          options={webmasterSites.map((s) => ({
                            value: s.host_id,
                            label: s.url || s.host_id,
                          }))}
                          onChange={(value) =>
                            mapDomain.mutate({ id: domain.id, webmaster_host_id: value })
                          }
                        />
                      </TableCell>
                      <TableCell>
                        <ResourceSelect
                          value={domain.search_console_site}
                          suggested={domain.suggested.search_console_site}
                          options={consoleSites.map((s) => ({
                            value: s.site_url,
                            label: s.site_url,
                          }))}
                          onChange={(value) =>
                            mapDomain.mutate({ id: domain.id, search_console_site: value })
                          }
                        />
                      </TableCell>
                      <TableCell>
                        <Input
                          className="w-32"
                          inputMode="numeric"
                          placeholder="номер"
                          value={counters[domain.id] ?? domain.metrika_counter_id?.toString() ?? ''}
                          onChange={(e) =>
                            setCounters((prev) => ({ ...prev, [domain.id]: e.target.value }))
                          }
                          onBlur={(e) => {
                            const raw = e.target.value.trim()
                            mapDomain.mutate({
                              id: domain.id,
                              metrika_counter_id: raw === '' ? null : Number(raw),
                            })
                          }}
                        />
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  )
}

function ConnectionCard({
  title,
  hint,
  connected,
  configured = true,
  reason,
  onConnect,
  onDisconnect,
}: {
  title: string
  hint: string
  connected: boolean
  configured?: boolean
  reason?: string
  onConnect: () => void
  onDisconnect?: () => void
}) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <CardTitle className="text-base">{title}</CardTitle>
        <Badge variant={connected ? 'default' : 'secondary'}>
          {connected ? 'подключено' : 'не подключено'}
        </Badge>
      </CardHeader>
      <CardContent className="space-y-3">
        <p className="text-sm text-muted-foreground">{hint}</p>
        {!connected && reason && <p className="text-sm text-amber-600">{reason}</p>}
        {!configured && (
          <p className="text-sm text-amber-600">
            Не заданы GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET и GOOGLE_REDIRECT_URI.
          </p>
        )}
        <div className="flex gap-2">
          <Button size="sm" onClick={onConnect} disabled={!configured}>
            <ExternalLink className="h-4 w-4 mr-2" />
            {connected ? 'Переподключить' : 'Подключить'}
          </Button>
          {connected && onDisconnect && (
            <Button size="sm" variant="outline" onClick={onDisconnect}>
              Отключить
            </Button>
          )}
        </div>
      </CardContent>
    </Card>
  )
}

function ResourceSelect({
  value,
  suggested,
  options,
  onChange,
}: {
  value: string | null
  suggested: string | null
  options: { value: string; label: string }[]
  onChange: (value: string | null) => void
}) {
  if (options.length === 0) {
    return <span className="text-sm text-muted-foreground">—</span>
  }

  return (
    <div className="space-y-1">
      <Select
        value={value ?? NONE}
        onValueChange={(next) => onChange(next === NONE ? null : next)}
      >
        <SelectTrigger className="w-56">
          <SelectValue placeholder="не привязан" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={NONE} label="не привязан">
            не привязан
          </SelectItem>
          {options.map((option) => (
            <SelectItem key={option.value} value={option.value} label={option.label}>
              {option.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      {!value && suggested && (
        <button
          type="button"
          className="text-xs text-primary hover:underline"
          onClick={() => onChange(suggested)}
        >
          похоже на этот ресурс — привязать
        </button>
      )}
    </div>
  )
}
