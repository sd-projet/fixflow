import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiList, patch, post } from '../services/api'
import { useAuth } from '../features/auth/AuthContext'
import { ROLE_LABEL } from '../lib/labels'
import type { Role, UserAccount } from '../types'

const ROLES = Object.keys(ROLE_LABEL) as Role[]
const mainRole = (u: UserAccount): Role => (u.roles.includes('ROLE_ADMIN') ? 'ROLE_ADMIN' : u.roles.includes('ROLE_DEV') ? 'ROLE_DEV' : 'ROLE_USER')

export default function UsersPage() {
  const qc = useQueryClient()
  const { me } = useAuth()
  const { data, isLoading } = useQuery({ queryKey: ['users', 'all'], queryFn: () => apiList<UserAccount>('/api/users?itemsPerPage=100') })
  const [form, setForm] = useState({ fullName: '', email: '', plainPassword: '', role: 'ROLE_USER' as Role })
  const refresh = () => qc.invalidateQueries({ queryKey: ['users'] })

  const create = useMutation({
    mutationFn: () => post('/api/users', { fullName: form.fullName, email: form.email, plainPassword: form.plainPassword, roles: [form.role] }),
    onSuccess: () => { setForm({ fullName: '', email: '', plainPassword: '', role: 'ROLE_USER' }); refresh() },
  })
  const update = useMutation({ mutationFn: ({ id, body }: { id: number; body: object }) => patch(`/api/users/${id}`, body), onSuccess: refresh })
  const submit = (e: FormEvent) => { e.preventDefault(); create.mutate() }

  return (
    <>
      <h2>Utilisateurs</h2>
      <form onSubmit={submit} className="card row">
        <input placeholder="Nom complet" value={form.fullName} onChange={(e) => setForm({ ...form, fullName: e.target.value })} required />
        <input placeholder="Email" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required />
        <input placeholder="Mot de passe (8 car. min.)" type="password" minLength={8} value={form.plainPassword} onChange={(e) => setForm({ ...form, plainPassword: e.target.value })} required />
        <select value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value as Role })}>
          {ROLES.map((r) => <option key={r} value={r}>{ROLE_LABEL[r]}</option>)}
        </select>
        <button disabled={create.isPending}>Créer le compte</button>
        {create.error && <span className="error">{(create.error as Error).message}</span>}
      </form>
      <div className="card" style={{ overflowX: 'auto' }}>
        {isLoading ? <p>Chargement…</p> : (
          <table>
            <thead><tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Statut</th></tr></thead>
            <tbody>{data?.items.map((u) => (
              <tr key={u.id}>
                <td>{u.fullName}</td><td>{u.email}</td>
                <td>
                  <select value={mainRole(u)} disabled={u.id === me?.id} onChange={(e) => update.mutate({ id: u.id, body: { roles: [e.target.value] } })}>
                    {ROLES.map((r) => <option key={r} value={r}>{ROLE_LABEL[r]}</option>)}
                  </select>
                </td>
                <td>
                  <button className={u.active ? 'danger' : 'secondary'} disabled={u.id === me?.id} onClick={() => update.mutate({ id: u.id, body: { active: !u.active } })}>
                    {u.active ? 'Désactiver' : 'Réactiver'}
                  </button>
                </td>
              </tr>
            ))}</tbody>
          </table>
        )}
        {update.error && <p className="error">{(update.error as Error).message}</p>}
      </div>
    </>
  )
}
