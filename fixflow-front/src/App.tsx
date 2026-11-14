import { NavLink, Navigate, Route, Routes } from 'react-router-dom'
import type { ReactNode } from 'react'
import { useAuth } from './features/auth/AuthContext'
import Notifications from './features/notifications/Notifications'
import LoginPage from './pages/LoginPage'
import LandingPage from './pages/LandingPage'
import DashboardPage from './pages/DashboardPage'
import TicketsPage from './pages/TicketsPage'
import TicketDetailPage from './pages/TicketDetailPage'
import UsersPage from './pages/UsersPage'
import ProfilePage from './pages/ProfilePage'
import { ROLE_LABEL } from './lib/labels'
import type { Role } from './types'

function RequireRole({ role, children }: { role: Role; children: ReactNode }) {
  const { has } = useAuth()
  return has(role) ? <>{children}</> : <Navigate to="/" replace />
}

export default function App() {
  const { me, loading, logout, has } = useAuth()
  if (loading) return <p className="container">Chargement…</p>
  if (!me) {
    // Visiteur non connecté : page de présentation, puis connexion.
    return (
      <Routes>
        <Route path="/" element={<LandingPage />} />
        <Route path="/login" element={<LoginPage />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    )
  }
  const role = has('ROLE_ADMIN') ? 'ROLE_ADMIN' : has('ROLE_DEV') ? 'ROLE_DEV' : 'ROLE_USER'
  return (
    <>
      <header className="topbar">
        <div className="container">
          <span className="brand">FixFlow</span>
          <nav>
            <NavLink to="/" end>Tableau de bord</NavLink>
            <NavLink to="/tickets">Incidents</NavLink>
            {has('ROLE_ADMIN') && <NavLink to="/users">Utilisateurs</NavLink>}
          </nav>
          <span className="spacer" />
          <Notifications />
          <NavLink to="/profile">{me.fullName} <span className="muted small">({ROLE_LABEL[role]})</span></NavLink>
          <button className="secondary" onClick={logout}>Déconnexion</button>
        </div>
      </header>
      <main className="container">
        <Routes>
          <Route path="/" element={<DashboardPage />} />
          <Route path="/tickets" element={<TicketsPage />} />
          <Route path="/tickets/:id" element={<TicketDetailPage />} />
          <Route path="/users" element={<RequireRole role="ROLE_ADMIN"><UsersPage /></RequireRole>} />
          <Route path="/profile" element={<ProfilePage />} />
          <Route path="*" element={<Navigate to="/" />} />
        </Routes>
      </main>
    </>
  )
}