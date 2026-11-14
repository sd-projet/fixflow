import { useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useAuth } from '../features/auth/AuthContext'

const DEMO: Record<string, string> = { admin: 'admin@fixflow.test', dev: 'dev@fixflow.test', user: 'user@fixflow.test' }

export default function LoginPage() {
  const { login } = useAuth()
  const [params] = useSearchParams()
  // /login?demo=admin|dev|user pré-remplit l'email du compte de démonstration.
  const [email, setEmail] = useState(DEMO[params.get('demo') ?? ''] ?? '')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setError('')
    try { await login(email, password) } catch (err) { setError((err as Error).message) } finally { setBusy(false) }
  }
  return (
    <div className="login card">
      <Link to="/" className="small">← Retour à l'accueil</Link>
      <h1 style={{ marginTop: 12 }}>FixFlow</h1>
      <p className="muted">Gestion des incidents et du support technique</p>
      <form onSubmit={submit}>
        <input value={email} onChange={(e) => setEmail(e.target.value)} placeholder="Email" type="email" autoComplete="username" required />
        <input value={password} onChange={(e) => setPassword(e.target.value)} placeholder="Mot de passe" type="password" autoComplete="current-password" required autoFocus={!!email} />
        {error && <p className="error">{error}</p>}
        <button disabled={busy}>Se connecter</button>
      </form>
      <p className="muted small">Démo : admin@fixflow.test, dev@fixflow.test ou user@fixflow.test — mot de passe « password ».</p>
    </div>
  )
}