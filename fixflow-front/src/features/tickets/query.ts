export interface TicketFilters {
  q: string; status: string; priority: string; category: string
  assignee: string // IRI /api/users/{id}
  from: string; to: string // dates YYYY-MM-DD
  archived: boolean
}

export const EMPTY_FILTERS: TicketFilters = { q: '', status: '', priority: '', category: '', assignee: '', from: '', to: '', archived: false }

/** Construit la query string de `GET /api/tickets` (filtres + pagination côté serveur). */
export function buildTicketQuery(f: TicketFilters, page: number, perPage: number): string {
  const p = new URLSearchParams({ page: String(page), itemsPerPage: String(perPage) })
  if (f.q.trim()) p.set('q', f.q.trim())
  for (const k of ['status', 'priority', 'category', 'assignee'] as const) if (f[k]) p.set(k, f[k])
  if (f.from) p.set('createdAt[after]', f.from)
  if (f.to) p.set('createdAt[before]', f.to)
  if (f.archived) p.set('archived', 'true')
  return p.toString()
}
