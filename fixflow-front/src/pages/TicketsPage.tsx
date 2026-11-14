import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiList, post } from '../services/api'
import { useAuth } from '../features/auth/AuthContext'
import { EMPTY_FILTERS, buildTicketQuery, type TicketFilters } from '../features/tickets/query'
import { CATEGORIES, CATEGORY_LABEL, PRIORITIES, PRIORITY_LABEL, STATUSES, STATUS_LABEL, formatDate } from '../lib/labels'
import { PriorityBadge, StatusBadge } from '../components/Badge'
import Pagination from '../components/Pagination'
import type { Category, Priority, Ticket, UserAccount } from '../types'

const PER_PAGE = 10

export default function TicketsPage() {
  const qc = useQueryClient()
  const { has } = useAuth()
  const [page, setPage] = useState(1)
  const [f, setF] = useState<TicketFilters>(EMPTY_FILTERS)
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({ title: '', description: '', category: 'bug' as Category, priority: 'normal' as Priority })

  const query = buildTicketQuery(f, page, PER_PAGE)
  const { data, isLoading, error } = useQuery({ queryKey: ['tickets', query], queryFn: () => apiList<Ticket>(`/api/tickets?${query}`) })
  const { data: users } = useQuery({
    queryKey: ['users', 'all'], enabled: has('ROLE_ADMIN'),
    queryFn: () => apiList<UserAccount>('/api/users?itemsPerPage=100'),
  })

  const create = useMutation({
    mutationFn: () => post('/api/tickets', form),
    onSuccess: () => {
      setForm({ ...form, title: '', description: '' })
      setShowForm(false)
      setPage(1)
      qc.invalidateQueries({ queryKey: ['tickets'] })
      qc.invalidateQueries({ queryKey: ['dashboard'] })
    },
  })
  const submit = (e: FormEvent) => { e.preventDefault(); create.mutate() }
  const set = (patch: Partial<TicketFilters>) => { setF({ ...f, ...patch }); setPage(1) }
  const hasFilters = JSON.stringify(f) !== JSON.stringify(EMPTY_FILTERS)

  return (
    <>
      <div className="row" style={{ justifyContent: 'space-between' }}>
        <h2>Incidents</h2>
        <button onClick={() => setShowForm(!showForm)}>{showForm ? 'Annuler' : '+ Déclarer un incident'}</button>
      </div>

      {showForm && (
        <form onSubmit={submit} className="card" style={{ display: 'grid', gap: 8 }}>
          <input placeholder="Titre" maxLength={200} value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} required />
          <textarea placeholder="Description détaillée (étapes pour reproduire, impact…)" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} required />
          <div className="row">
            <select value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value as Category })}>
              {CATEGORIES.map((o) => <option key={o} value={o}>{CATEGORY_LABEL[o]}</option>)}
            </select>
            <select value={form.priority} onChange={(e) => setForm({ ...form, priority: e.target.value as Priority })}>
              {PRIORITIES.map((o) => <option key={o} value={o}>{PRIORITY_LABEL[o]}</option>)}
            </select>
            <button disabled={create.isPending}>Déclarer</button>
          </div>
          {create.error && <span className="error">{(create.error as Error).message}</span>}
        </form>
      )}

      <div className="card row">
        <input placeholder="Référence ou titre…" value={f.q} onChange={(e) => set({ q: e.target.value })} />
        <select value={f.status} onChange={(e) => set({ status: e.target.value })}>
          <option value="">Tous statuts</option>{STATUSES.map((o) => <option key={o} value={o}>{STATUS_LABEL[o]}</option>)}
        </select>
        <select value={f.priority} onChange={(e) => set({ priority: e.target.value })}>
          <option value="">Toutes priorités</option>{PRIORITIES.map((o) => <option key={o} value={o}>{PRIORITY_LABEL[o]}</option>)}
        </select>
        <select value={f.category} onChange={(e) => set({ category: e.target.value })}>
          <option value="">Toutes catégories</option>{CATEGORIES.map((o) => <option key={o} value={o}>{CATEGORY_LABEL[o]}</option>)}
        </select>
        {has('ROLE_ADMIN') && (
          <select value={f.assignee} onChange={(e) => set({ assignee: e.target.value })}>
            <option value="">Tous développeurs</option>
            {users?.items.filter((u) => u.roles.includes('ROLE_DEV')).map((u) => <option key={u.id} value={`/api/users/${u.id}`}>{u.fullName}</option>)}
          </select>
        )}
        <label className="small muted">Du <input type="date" value={f.from} onChange={(e) => set({ from: e.target.value })} /></label>
        <label className="small muted">au <input type="date" value={f.to} onChange={(e) => set({ to: e.target.value })} /></label>
        {has('ROLE_ADMIN') && (
          <label className="small"><input type="checkbox" checked={f.archived} onChange={(e) => set({ archived: e.target.checked })} /> Archivés</label>
        )}
        {hasFilters && <button className="link" onClick={() => { setF(EMPTY_FILTERS); setPage(1) }}>Réinitialiser</button>}
      </div>

      <div className="card" style={{ overflowX: 'auto' }}>
        {isLoading ? <p>Chargement…</p> : error ? <p className="error">{(error as Error).message}</p> : (
          <table>
            <thead><tr><th>Réf.</th><th>Titre</th><th>Statut</th><th>Priorité</th><th>Assigné</th><th>Créé le</th></tr></thead>
            <tbody>
              {data?.items.map((t) => (
                <tr key={t.id}>
                  <td><Link to={`/tickets/${t.id}`}>{t.reference}</Link></td><td>{t.title}</td>
                  <td><StatusBadge status={t.status} /></td><td><PriorityBadge priority={t.priority} /></td>
                  <td>{t.assignee?.fullName ?? '—'}</td><td>{formatDate(t.createdAt)}</td>
                </tr>
              ))}
              {data?.items.length === 0 && <tr><td colSpan={6} className="muted">Aucun incident ne correspond.</td></tr>}
            </tbody>
          </table>
        )}
        <Pagination page={page} total={data?.total ?? 0} perPage={PER_PAGE} onChange={setPage} />
      </div>
    </>
  )
}
