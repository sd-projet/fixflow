import { describe, expect, it } from 'vitest'
import { EMPTY_FILTERS, buildTicketQuery } from './query'

describe('buildTicketQuery', () => {
  it('ajoute la pagination seule sans filtre', () => {
    expect(buildTicketQuery(EMPTY_FILTERS, 2, 10)).toBe('page=2&itemsPerPage=10')
  })

  it('traduit les filtres en paramètres API Platform', () => {
    const qs = new URLSearchParams(buildTicketQuery(
      { ...EMPTY_FILTERS, q: ' FF-2026 ', status: 'new', priority: 'high', assignee: '/api/users/3', from: '2026-01-01', to: '2026-02-01', archived: true }, 1, 10))
    expect(qs.get('q')).toBe('FF-2026')
    expect(qs.get('status')).toBe('new')
    expect(qs.get('priority')).toBe('high')
    expect(qs.get('assignee')).toBe('/api/users/3')
    expect(qs.get('createdAt[after]')).toBe('2026-01-01')
    expect(qs.get('createdAt[before]')).toBe('2026-02-01')
    expect(qs.get('archived')).toBe('true')
  })
})
