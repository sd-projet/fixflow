import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { apiList, patch } from '../../services/api'
import { formatDateTime } from '../../lib/labels'
import type { Notif } from '../../types'

export default function Notifications() {
  const qc = useQueryClient()
  const nav = useNavigate()
  const [open, setOpen] = useState(false)
  const { data } = useQuery({ queryKey: ['notifs'], refetchInterval: 15000, queryFn: () => apiList<Notif>('/api/notifications?itemsPerPage=20') })
  const items = data?.items ?? []
  const unread = items.filter((n) => !n.read)
  const refresh = () => qc.invalidateQueries({ queryKey: ['notifs'] })

  const go = async (n: Notif) => {
    if (!n.read) await patch(`/api/notifications/${n.id}`, { read: true })
    refresh()
    setOpen(false)
    nav(`/tickets/${n.ticket.split('/').pop()}`)
  }
  const readAll = async () => {
    await Promise.all(unread.map((n) => patch(`/api/notifications/${n.id}`, { read: true })))
    refresh()
  }

  return (
    <div className="notif-menu">
      <button className="secondary" onClick={() => setOpen(!open)} aria-label="Notifications">🔔 {unread.length}</button>
      {open && (
        <div className="notif-panel">
          <div className="row" style={{ justifyContent: 'space-between', marginBottom: 6 }}>
            <strong>Notifications</strong>
            {unread.length > 0 && <button className="link" onClick={readAll}>Tout marquer comme lu</button>}
          </div>
          {items.length === 0 ? <p className="muted">Aucune notification.</p> : (
            <ul>
              {items.map((n) => (
                <li key={n.id} className={n.read ? '' : 'unread'}>
                  <button className="link" style={{ textAlign: 'left' }} onClick={() => go(n)}>{n.message}</button>
                  <div className="muted small">{formatDateTime(n.createdAt)}</div>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  )
}
