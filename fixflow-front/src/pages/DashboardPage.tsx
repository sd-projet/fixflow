import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { api } from '../services/api'
import { useAuth } from '../features/auth/AuthContext'
import { PriorityBadge, StatusBadge } from '../components/Badge'
import { PRIORITY_LABEL, STATUS_LABEL, formatDateTime } from '../lib/labels'
import type { Dashboard, TicketSummary } from '../types'

function Bars({ rows, labels }: { rows: { key: string; count: number }[]; labels: Record<string, string> }) {
  const max = Math.max(1, ...rows.map((r) => r.count))
  if (!rows.length) return <p className="muted">Aucune donnée.</p>
  return <>{rows.map((r) => (
    <div className="bar-row" key={r.key}>
      <span>{labels[r.key] ?? r.key}</span><div className="bar" style={{ width: `${(r.count / max) * 100}%` }} /><strong>{r.count}</strong>
    </div>
  ))}</>
}

function TicketList({ title, items }: { title: string; items: TicketSummary[] }) {
  return (
    <section className="card">
      <h3>{title}</h3>
      {items.length === 0 ? <p className="muted">Aucun incident.</p> : (
        <table><tbody>{items.map((t) => (
          <tr key={t.id}>
            <td><Link to={`/tickets/${t.id}`}>{t.reference}</Link></td><td>{t.title}</td>
            <td><StatusBadge status={t.status} /> <PriorityBadge priority={t.priority} /></td>
            <td className="muted small">{formatDateTime(t.updatedAt)}</td>
          </tr>
        ))}</tbody></table>
      )}
    </section>
  )
}

export default function DashboardPage() {
  const { has } = useAuth()
  const { data, isLoading, error } = useQuery({ queryKey: ['dashboard'], queryFn: () => api<Dashboard>('/api/dashboard') })
  if (isLoading) return <p>Chargement…</p>
  if (error || !data) return <p className="error">Impossible de charger le tableau de bord.</p>

  const scope = has('ROLE_ADMIN') ? 'Vue globale' : has('ROLE_DEV') ? 'Vos incidents assignés' : 'Vos incidents déclarés'
  const cards: [string, number | string, boolean?][] = [
    ['Total', data.total], ['Ouverts', data.open], ['En cours', data.inProgress], ['En attente', data.pending],
    ['Critiques', data.critical, data.critical > 0], ['Résolus', data.resolved],
    ...(data.avgResolutionHours !== null ? [['Résolution moy.', `${data.avgResolutionHours} h`] as [string, string]] : []),
  ]
  return (
    <>
      <h2>Tableau de bord</h2>
      <p className="muted">{scope}</p>
      <div className="grid" style={{ marginBottom: 16 }}>
        {cards.map(([label, value, danger]) => <div key={label} className={`card stat${danger ? ' danger' : ''}`} style={{ marginBottom: 0 }}><small>{label}</small><strong>{value}</strong></div>)}
      </div>
      {has('ROLE_ADMIN') && (
        <div className="grid" style={{ gridTemplateColumns: 'repeat(auto-fit,minmax(300px,1fr))' }}>
          <section className="card"><h3>Par statut</h3><Bars rows={data.byStatus} labels={STATUS_LABEL} /></section>
          <section className="card"><h3>Par priorité</h3><Bars rows={data.byPriority} labels={PRIORITY_LABEL} /></section>
        </div>
      )}
      <TicketList title="Incidents prioritaires ouverts" items={data.urgent} />
      <TicketList title={has('ROLE_USER') && !has('ROLE_DEV') && !has('ROLE_ADMIN') ? 'Dernières mises à jour' : 'Récemment modifiés'} items={data.recent} />
    </>
  )
}
