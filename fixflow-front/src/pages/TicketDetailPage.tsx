import { useState, type FormEvent } from 'react'
import { useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, apiList, del, patch, post, upload } from '../services/api'
import { useAuth } from '../features/auth/AuthContext'
import { CATEGORIES, CATEGORY_LABEL, PRIORITIES, PRIORITY_LABEL, STATUSES, STATUS_LABEL, describeChange, formatDateTime, formatSize } from '../lib/labels'
import { PriorityBadge, StatusBadge } from '../components/Badge'
import type { Attachment, Comment, History, Ticket, UserAccount } from '../types'

export default function TicketDetailPage() {
  const { id } = useParams()
  const { me, has } = useAuth()
  const qc = useQueryClient()
  const iri = `/api/tickets/${id}`
  const key = ['ticket', id]
  const refresh = () => { qc.invalidateQueries({ queryKey: key }); qc.invalidateQueries({ queryKey: ['tickets'] }); qc.invalidateQueries({ queryKey: ['dashboard'] }) }

  const { data: t, error } = useQuery({ queryKey: key, queryFn: () => api<Ticket>(iri) })
  const { data: comments } = useQuery({ queryKey: [...key, 'comments'], queryFn: () => apiList<Comment>(`/api/comments?ticket=${iri}&itemsPerPage=100`) })
  const { data: history } = useQuery({ queryKey: [...key, 'history'], queryFn: () => apiList<History>(`/api/ticket_histories?ticket=${iri}&itemsPerPage=100`) })
  const { data: files } = useQuery({ queryKey: [...key, 'files'], queryFn: () => api<Attachment[]>(`${iri}/attachments`) })
  const { data: users } = useQuery({ queryKey: ['users', 'all'], enabled: has('ROLE_ADMIN'), queryFn: () => apiList<UserAccount>('/api/users?itemsPerPage=100') })

  const [text, setText] = useState('')
  const [editing, setEditing] = useState<{ title: string; description: string } | null>(null)
  const [editComment, setEditComment] = useState<{ id: number; content: string } | null>(null)

  const update = useMutation({ mutationFn: (b: object) => patch(iri, b), onSuccess: () => { setEditing(null); refresh() } })
  const addComment = useMutation({ mutationFn: () => post('/api/comments', { ticket: iri, content: text }), onSuccess: () => { setText(''); refresh() } })
  const saveComment = useMutation({ mutationFn: () => patch(`/api/comments/${editComment!.id}`, { content: editComment!.content }), onSuccess: () => { setEditComment(null); refresh() } })
  const removeComment = useMutation({ mutationFn: (cid: number) => del(`/api/comments/${cid}`), onSuccess: refresh })
  const addFile = useMutation({ mutationFn: (f: File) => upload(`${iri}/attachments`, f), onSuccess: refresh })
  const removeFile = useMutation({ mutationFn: (fid: number) => del(`/api/attachments/${fid}`), onSuccess: refresh })

  if (error) return <p className="error">{(error as Error).message}</p>
  if (!t || !me) return <p>Chargement…</p>

  const admin = has('ROLE_ADMIN')
  const canStatus = admin || has('ROLE_DEV')
  const canEditContent = admin || (!has('ROLE_DEV') && t.creator.id === me.id && t.status !== 'closed')
  const errors = [update, addComment, saveComment, removeComment, addFile, removeFile].map((m) => m.error as Error | null).filter(Boolean)

  const submitComment = (e: FormEvent) => { e.preventDefault(); addComment.mutate() }

  return (
    <>
      <h2>{t.reference} — {t.title} {t.archived && <span className="badge">Archivé</span>}</h2>
      <div className="card">
        <div className="row" style={{ marginBottom: 8 }}>
          <StatusBadge status={t.status} /> <PriorityBadge priority={t.priority} /> <span className="badge">{CATEGORY_LABEL[t.category]}</span>
        </div>
        <p className="muted small">
          Créé par {t.creator.fullName} le {formatDateTime(t.createdAt)} · Assigné à {t.assignee?.fullName ?? '—'} · Mis à jour le {formatDateTime(t.updatedAt)}
          {t.resolvedAt && <> · Résolu le {formatDateTime(t.resolvedAt)}</>}
        </p>

        {editing ? (
          <div style={{ display: 'grid', gap: 8 }}>
            <input value={editing.title} maxLength={200} onChange={(e) => setEditing({ ...editing, title: e.target.value })} />
            <textarea value={editing.description} onChange={(e) => setEditing({ ...editing, description: e.target.value })} />
            <div className="row">
              <button onClick={() => update.mutate(editing)} disabled={update.isPending}>Enregistrer</button>
              <button className="secondary" onClick={() => setEditing(null)}>Annuler</button>
            </div>
          </div>
        ) : (
          <>
            <p style={{ whiteSpace: 'pre-wrap' }}>{t.description}</p>
            {canEditContent && <button className="link" onClick={() => setEditing({ title: t.title, description: t.description })}>Modifier le titre / la description</button>}
          </>
        )}

        {(canStatus || admin) && (
          <div className="row" style={{ marginTop: 12 }}>
            {canStatus && (
              <label>Statut{' '}
                <select value={t.status} onChange={(e) => update.mutate({ status: e.target.value })}>
                  {STATUSES.map((s) => <option key={s} value={s}>{STATUS_LABEL[s]}</option>)}
                </select>
              </label>
            )}
            {admin && (
              <>
                <label>Priorité{' '}
                  <select value={t.priority} onChange={(e) => update.mutate({ priority: e.target.value })}>
                    {PRIORITIES.map((s) => <option key={s} value={s}>{PRIORITY_LABEL[s]}</option>)}
                  </select>
                </label>
                <label>Catégorie{' '}
                  <select value={t.category} onChange={(e) => update.mutate({ category: e.target.value })}>
                    {CATEGORIES.map((s) => <option key={s} value={s}>{CATEGORY_LABEL[s]}</option>)}
                  </select>
                </label>
                <label>Assigné à{' '}
                  <select value={t.assignee ? `/api/users/${t.assignee.id}` : ''} onChange={(e) => update.mutate({ assignee: e.target.value || null })}>
                    <option value="">—</option>
                    {users?.items.filter((u) => u.active && u.roles.includes('ROLE_DEV')).map((u) => <option key={u.id} value={`/api/users/${u.id}`}>{u.fullName}</option>)}
                  </select>
                </label>
                <button className="secondary" onClick={() => update.mutate({ archived: !t.archived })}>{t.archived ? 'Désarchiver' : 'Archiver'}</button>
              </>
            )}
          </div>
        )}
        {errors.map((e, i) => <p key={i} className="error">{e!.message}</p>)}
      </div>

      <section className="card">
        <h3>Pièces jointes</h3>
        {files?.length ? (
          <ul>{files.map((f) => (
            <li key={f.id}>
              <a href={`/api/attachments/${f.id}/download`}>{f.name}</a> <span className="muted small">({formatSize(f.size)}, {f.uploader.fullName})</span>{' '}
              {(admin || f.uploader.id === me.id) && <button className="link" onClick={() => removeFile.mutate(f.id)}>Supprimer</button>}
            </li>
          ))}</ul>
        ) : <p className="muted">Aucune pièce jointe.</p>}
        <input type="file" accept="image/png,image/jpeg,image/gif,image/webp,application/pdf,text/plain"
          onChange={(e) => { const file = e.target.files?.[0]; if (file) addFile.mutate(file); e.target.value = '' }} />
        <p className="muted small">Images, PDF ou texte — 5 Mo maximum.</p>
      </section>

      <section className="card">
        <h3>Commentaires</h3>
        {comments?.items.length === 0 && <p className="muted">Aucun commentaire.</p>}
        {comments?.items.map((c) => (
          <div className="comment" key={c.id}>
            <b>{c.author.fullName}</b> <small className="muted">{formatDateTime(c.createdAt)}{c.updatedAt && ' (modifié)'}</small>
            {editComment?.id === c.id ? (
              <div style={{ display: 'grid', gap: 8, marginTop: 6 }}>
                <textarea value={editComment.content} onChange={(e) => setEditComment({ ...editComment, content: e.target.value })} />
                <div className="row"><button onClick={() => saveComment.mutate()}>Enregistrer</button><button className="secondary" onClick={() => setEditComment(null)}>Annuler</button></div>
              </div>
            ) : (
              <>
                <div>{c.content}</div>
                <div className="row small">
                  {c.author.id === me.id && <button className="link" onClick={() => setEditComment({ id: c.id, content: c.content })}>Modifier</button>}
                  {(c.author.id === me.id || admin) && <button className="link" onClick={() => window.confirm('Supprimer ce commentaire ?') && removeComment.mutate(c.id)}>Supprimer</button>}
                </div>
              </>
            )}
          </div>
        ))}
        <form onSubmit={submitComment} className="row" style={{ marginTop: 12 }}>
          <input style={{ flex: 1 }} value={text} onChange={(e) => setText(e.target.value)} placeholder="Ajouter un commentaire" required />
          <button disabled={addComment.isPending}>Envoyer</button>
        </form>
      </section>

      <section className="card">
        <h3>Historique</h3>
        <ul>{history?.items.map((h) => <li key={h.id}><span className="muted small">{formatDateTime(h.createdAt)}</span> — <b>{h.actor.fullName}</b> {describeChange(h)}</li>)}</ul>
      </section>
    </>
  )
}
