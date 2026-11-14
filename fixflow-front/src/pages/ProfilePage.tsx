import { useState, type FormEvent } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { patch } from '../services/api'
import { useAuth } from '../features/auth/AuthContext'
import type { Me } from '../types'

export default function ProfilePage() {
  const { me, logout } = useAuth()
  const qc = useQueryClient()
  const [fullName, setFullName] = useState(me?.fullName ?? '')
  const [password, setPassword] = useState('')
  const [done, setDone] = useState('')

  const save = useMutation({
    mutationFn: () => patch(`/api/users/${me!.id}`, { fullName, ...(password ? { plainPassword: password } : {}) }),
    onSuccess: () => {
      if (password) {
        // Le changement de mot de passe invalide la session : on se reconnecte.
        setDone('Mot de passe modifié. Reconnexion…')
        setTimeout(logout, 1200)
      } else {
        qc.setQueryData<Me | null>(['me'], (m) => (m ? { ...m, fullName } : m))
        setDone('Profil mis à jour.')
      }
      setPassword('')
    },
  })
  const submit = (e: FormEvent) => { e.preventDefault(); setDone(''); save.mutate() }

  return (
    <>
      <h2>Mon profil</h2>
      <form onSubmit={submit} className="card" style={{ display: 'grid', gap: 10, maxWidth: 420 }}>
        <label>Email<input value={me?.email ?? ''} disabled style={{ width: '100%' }} /></label>
        <label>Nom complet<input value={fullName} onChange={(e) => setFullName(e.target.value)} required style={{ width: '100%' }} /></label>
        <label>Nouveau mot de passe <span className="muted small">(laisser vide pour ne pas changer)</span>
          <input type="password" minLength={8} value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="new-password" style={{ width: '100%' }} />
        </label>
        {save.error && <p className="error">{(save.error as Error).message}</p>}
        {done && <p className="success">{done}</p>}
        <button disabled={save.isPending}>Enregistrer</button>
      </form>
    </>
  )
}
