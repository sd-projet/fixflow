import { useEffect, useRef, useState, type ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { PRIORITY_LABEL, STATUS_LABEL } from '../lib/labels'
import type { Priority, Status } from '../types'
import './landing.css'

const reducedMotion = () => typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

/* ---------- Petits utilitaires d'animation (aucune dépendance) ---------- */

function useInView<T extends Element>(threshold = 0.2) {
  const ref = useRef<T>(null)
  const [seen, setSeen] = useState(false)
  useEffect(() => {
    const el = ref.current
    if (!el) return
    if (!('IntersectionObserver' in window)) { setSeen(true); return }
    const io = new IntersectionObserver(([e]) => { if (e.isIntersecting) { setSeen(true); io.disconnect() } }, { threshold })
    io.observe(el)
    return () => io.disconnect()
  }, [threshold])
  return [ref, seen] as const
}

function Reveal({ children, delay = 0 }: { children: ReactNode; delay?: number }) {
  const [ref, seen] = useInView<HTMLDivElement>(0.12)
  return <div ref={ref} className={`reveal${seen ? ' in' : ''}`} style={{ transitionDelay: `${delay}ms` }}>{children}</div>
}

function CountUp({ to, suffix = '' }: { to: number; suffix?: string }) {
  const [ref, seen] = useInView<HTMLSpanElement>(0.4)
  const [value, setValue] = useState(0)
  useEffect(() => {
    if (!seen) return
    if (reducedMotion()) { setValue(to); return }
    let raf = 0
    const t0 = performance.now()
    const tick = (t: number) => {
      const p = Math.min(1, (t - t0) / 1300)
      setValue(Math.round(to * (1 - Math.pow(1 - p, 3))))
      if (p < 1) raf = requestAnimationFrame(tick)
    }
    raf = requestAnimationFrame(tick)
    return () => cancelAnimationFrame(raf)
  }, [seen, to])
  return <span ref={ref}>{value}{suffix}</span>
}

/** Fait avancer un compteur à intervalle régulier (désactivé si l'utilisateur préfère moins d'animations). */
function useTicker(ms: number, enabled = true) {
  const [tick, setTick] = useState(0)
  useEffect(() => {
    if (!enabled || reducedMotion()) return
    const id = setInterval(() => setTick((t) => t + 1), ms)
    return () => clearInterval(id)
  }, [ms, enabled])
  return [tick, setTick] as const
}

/* ---------- Icônes ---------- */

const ICONS: Record<string, string> = {
  inbox: 'M22 12h-6l-2 3h-4l-2-3H2 M5.5 5h13l3.5 7v6a2 2 0 0 1-2 2h-16a2 2 0 0 1-2-2v-6z',
  users: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z M22 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75',
  message: 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z',
  history: 'M12 8v4l3 2 M3 12a9 9 0 1 0 3-6.7 M3 4v5h5',
  chart: 'M3 3v18h18 M7 14l4-4 3 3 5-6',
  bell: 'M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9 M13.7 21a2 2 0 0 1-3.4 0',
  check: 'M20 6L9 17l-5-5',
  logo: 'M4 12l5 5L20 6',
}
const Icon = ({ name, size = 22 }: { name: keyof typeof ICONS; size?: number }) => (
  <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
    <path d={ICONS[name]} />
  </svg>
)

/* ---------- Maquette animée du tableau de bord ---------- */

const FLOW: Status[] = ['new', 'in_progress', 'pending', 'resolved']
const MOCK_TICKETS: { ref: string; title: string; priority: Priority }[] = [
  { ref: 'FF-2026-A1B2C3', title: "Erreur 500 à la création d'un client", priority: 'critical' },
  { ref: 'FF-2026-D4E5F6', title: 'Page de facturation très lente', priority: 'high' },
  { ref: 'FF-2026-0A9B8C', title: 'Export PDF : accents mal affichés', priority: 'normal' },
]

function DashboardMock() {
  const [tick] = useTicker(2400)
  const rows = MOCK_TICKETS.map((t, i) => ({ ...t, status: FLOW[(tick + i * 2) % FLOW.length] }))
  const count = (s: Status) => rows.filter((r) => r.status === s).length
  const open = rows.filter((r) => r.status !== 'resolved').length
  const critical = rows.filter((r) => r.priority === 'critical' && r.status !== 'resolved').length
  const heights = FLOW.map((s) => 18 + count(s) * 26)

  return (
    <div className="lp-mock-wrap">
      <div className="lp-mock" aria-hidden="true">
        <div className="lp-mock-bar"><i /><i /><i /><span>FixFlow — Tableau de bord</span></div>
        <div className="lp-mock-body">
          <div className="lp-tiles">
            <div className="lp-tile"><small>Ouverts</small><b>{open}</b></div>
            <div className="lp-tile crit"><small>Critiques</small><b>{critical}</b></div>
            <div className="lp-tile"><small>Résolus</small><b>{count('resolved')}</b></div>
          </div>
          {rows.map((r) => (
            <div className="lp-row" key={r.ref}>
              <div>{r.title}<small>{r.ref} · {PRIORITY_LABEL[r.priority]}</small></div>
              <span className={`badge ${r.status}`}>{STATUS_LABEL[r.status]}</span>
            </div>
          ))}
          <div className="lp-bars" style={{ marginBottom: 14 }}>
            {FLOW.map((s, i) => <div key={s} style={{ height: heights[i] }}><span>{STATUS_LABEL[s]}</span></div>)}
          </div>
        </div>
      </div>
      <div className="lp-float-card"><span className="lp-dot" /> <span><b>Notification</b><br />Incident attribué à Dev Demo</span></div>
    </div>
  )
}

/* ---------- Contenu ---------- */

const FEATURES: { icon: keyof typeof ICONS; title: string; text: string }[] = [
  { icon: 'inbox', title: 'Tous vos incidents au même endroit', text: 'Déclarez, catégorisez et priorisez. Recherche instantanée, filtres multiples et pagination serveur, même avec des milliers de tickets.' },
  { icon: 'users', title: 'Attribution intelligente', text: "Chaque nouvel incident est confié au développeur le moins chargé. L'administrateur garde la main pour réassigner." },
  { icon: 'message', title: 'Échanges et pièces jointes', text: 'Discussion par ticket, captures d’écran et fichiers contrôlés (type et taille vérifiés) pour diagnostiquer plus vite.' },
  { icon: 'history', title: 'Traçabilité complète', text: 'Qui a changé le statut ? Qui a modifié la priorité ? Quand ? Chaque action est journalisée et consultable.' },
  { icon: 'chart', title: 'Tableaux de bord par rôle', text: 'Volumes, répartition par statut et priorité, temps moyen de résolution : chacun voit ce qui compte pour lui.' },
  { icon: 'bell', title: 'Notifications et alertes', text: 'Attribution, commentaire, changement de statut, incident critique. Détection automatique des tickets restés sans activité.' },
]

const STEPS = [
  { title: 'Déclarer', short: 'L’utilisateur signale le problème en quelques champs.', detail: 'Un titre, une description, une catégorie et une priorité. Une référence unique est générée et les pièces jointes sont acceptées.', status: 'new' as Status, chips: ['Référence unique', 'Catégorie', 'Pièces jointes'] },
  { title: 'Attribuer', short: 'Le bon développeur est notifié immédiatement.', detail: 'L’attribution automatique choisit le développeur le moins chargé ; un administrateur peut réassigner et ajuster la priorité à tout moment.', status: 'in_progress' as Status, chips: ['Attribution auto', 'Notification', 'Priorité ajustable'] },
  { title: 'Résoudre', short: 'Échanges, suivi du statut, mise en attente si besoin.', detail: 'Le développeur commente, fait évoluer le statut (en cours, en attente, résolu) et l’utilisateur répond directement dans le ticket.', status: 'pending' as Status, chips: ['Commentaires', 'Statuts', 'Réponses'] },
  { title: 'Tracer', short: 'Tout est historisé, mesuré et auditable.', detail: 'Chaque changement est conservé avec son auteur et sa date. Le tableau de bord calcule le temps moyen de résolution.', status: 'resolved' as Status, chips: ['Historique', 'Statistiques', 'Audit'] },
]

const ROLES = [
  { key: 'user', label: 'Utilisateur', title: 'Signaler sans friction', text: 'Un espace simple pour déclarer un problème et savoir où il en est.', demo: 'user',
    points: ['Déclarer un incident avec pièces jointes', 'Suivre uniquement ses propres demandes', 'Répondre aux questions de l’équipe technique', 'Consulter l’historique de ses demandes'] },
  { key: 'dev', label: 'Développeur', title: 'Se concentrer sur la résolution', text: 'Une vue claire des incidents qui lui sont attribués, sans bruit.', demo: 'dev',
    points: ['Tableau de bord des incidents assignés et prioritaires', 'Changement de statut et commentaires', 'Notification à chaque nouvelle attribution', 'Historique de chaque ticket'] },
  { key: 'admin', label: 'Administrateur', title: 'Piloter et superviser', text: 'La vision globale pour répartir la charge et garder le contrôle.', demo: 'admin',
    points: ['Statistiques globales et temps moyen de résolution', 'Attribution, priorités et catégories', 'Gestion des comptes : création, rôles, désactivation', 'Archivage et alertes sur les tickets en souffrance'] },
]

/* ---------- Sections ---------- */

function Header() {
  const [scrolled, setScrolled] = useState(false)
  useEffect(() => {
    const on = () => setScrolled(window.scrollY > 8)
    on()
    window.addEventListener('scroll', on, { passive: true })
    return () => window.removeEventListener('scroll', on)
  }, [])
  return (
    <header className={`lp-header${scrolled ? ' scrolled' : ''}`}>
      <div className="lp-wrap">
        <a href="#top" className="lp-logo"><span className="lp-logo-mark"><Icon name="logo" size={18} /></span>FixFlow</a>
        <nav className="lp-nav" aria-label="Sections">
          <a href="#fonctionnalites">Fonctionnalités</a><a href="#parcours">Parcours</a><a href="#roles">Rôles</a><a href="#technique">Technique</a>
        </nav>
        <span className="lp-spacer" />
        <Link to="/login" className="lp-btn light sm">Se connecter</Link>
      </div>
    </header>
  )
}

function Hero() {
  const words = ['bugs', 'lenteurs', 'failles', 'pannes']
  const [tick] = useTicker(2200)
  return (
    <section className="lp-hero" id="top">
      <div className="lp-blob a" /><div className="lp-blob b" />
      <div className="lp-wrap lp-hero-grid">
        <div>
          <span className="lp-pill"><span className="lp-dot" /> Gestion des incidents et du support technique</span>
          <h1>Reprenez le contrôle de vos <span className="lp-rot" key={tick}>{words[tick % words.length]}</span></h1>
          <p className="lead">FixFlow centralise les incidents techniques, les confie au bon développeur et garde la trace de chaque action. Une seule plateforme pour les utilisateurs, les développeurs et les administrateurs.</p>
          <div className="lp-cta">
            <Link to="/login" className="lp-btn primary">Accéder à la plateforme →</Link>
            <a href="#fonctionnalites" className="lp-btn ghost">Découvrir les fonctionnalités</a>
          </div>
          <div className="lp-trust"><span>Permissions par rôle</span><span>Historique d'audit</span><span>Notifications en temps réel</span></div>
        </div>
        <DashboardMock />
      </div>
    </section>
  )
}

function Stats() {
  const items: [number, string, string][] = [[3, '', 'rôles aux droits distincts'], [5, '', 'statuts de suivi'], [4, '', 'niveaux de priorité'], [100, ' %', 'des modifications tracées']]
  return (
    <section className="lp-stats"><div className="lp-wrap">
      {items.map(([n, suffix, label]) => <div key={label}><b><CountUp to={n} suffix={suffix} /></b><span>{label}</span></div>)}
    </div></section>
  )
}

function Features() {
  return (
    <section className="lp-section" id="fonctionnalites"><div className="lp-wrap">
      <Reveal><div className="lp-head center">
        <span className="lp-eyebrow">Fonctionnalités</span>
        <h2 className="lp-title">Tout ce qu'il faut pour traiter un incident de bout en bout</h2>
        <p className="lp-sub">De la déclaration à la clôture, sans tableur, sans e-mails perdus.</p>
      </div></Reveal>
      <div className="lp-features">
        {FEATURES.map((f, i) => (
          <Reveal key={f.title} delay={(i % 3) * 90}>
            <article className="lp-feature"><div className="lp-ico"><Icon name={f.icon} /></div><h3>{f.title}</h3><p>{f.text}</p></article>
          </Reveal>
        ))}
      </div>
    </div></section>
  )
}

function Journey() {
  const [paused, setPaused] = useState(false)
  const [tick, setTick] = useTicker(3600, !paused)
  const active = tick % STEPS.length
  const s = STEPS[active]
  const stepIdx = FLOW.indexOf(s.status)
  return (
    <section className="lp-section alt" id="parcours"><div className="lp-wrap">
      <Reveal><div className="lp-head">
        <span className="lp-eyebrow">Parcours d'un incident</span>
        <h2 className="lp-title">Quatre étapes, zéro zone d'ombre</h2>
        <p className="lp-sub">Cliquez sur une étape pour la découvrir.</p>
      </div></Reveal>
      <div className="lp-flow" onMouseEnter={() => setPaused(true)} onMouseLeave={() => setPaused(false)}>
        <div className="lp-steps">
          {STEPS.map((st, i) => (
            <button key={st.title} className={`lp-step${i === active ? ' on' : ''}`} onClick={() => setTick(i)} aria-pressed={i === active}>
              <span className="lp-num">{i + 1}</span><span><b>{st.title}</b><span>{st.short}</span></span>
            </button>
          ))}
        </div>
        <div className="lp-panel">
          <div key={active} className="lp-panel-in">
            <span className={`badge ${s.status}`}>{STATUS_LABEL[s.status]}</span>
            <h3>{s.title}</h3>
            <p>{s.detail}</p>
            <div className="lp-chips">{s.chips.map((c) => <span className="lp-chip" key={c}>{c}</span>)}</div>
          </div>
          <div className="lp-timeline" aria-hidden="true">
            {FLOW.map((f, i) => (
              <div key={f} style={{ display: 'contents' }}>
                <span className={`lp-tl-dot${i <= stepIdx ? ' done' : ''}`} />
                {i < FLOW.length - 1 && <span className="lp-tl-line"><i style={{ transform: `scaleX(${i < stepIdx ? 1 : 0})` }} /></span>}
              </div>
            ))}
          </div>
          <div className="lp-tl-labels" aria-hidden="true">{FLOW.map((f) => <span key={f}>{STATUS_LABEL[f]}</span>)}</div>
        </div>
      </div>
    </div></section>
  )
}

function Roles() {
  const [key, setKey] = useState('user')
  const role = ROLES.find((r) => r.key === key)!
  return (
    <section className="lp-section" id="roles"><div className="lp-wrap">
      <Reveal><div className="lp-head">
        <span className="lp-eyebrow">Pour chaque rôle</span>
        <h2 className="lp-title">Une interface adaptée à ce que chacun doit faire</h2>
      </div></Reveal>
      <div className="lp-tabs" role="tablist">
        {ROLES.map((r) => <button key={r.key} role="tab" aria-selected={r.key === key} className={`lp-tab${r.key === key ? ' on' : ''}`} onClick={() => setKey(r.key)}>{r.label}</button>)}
      </div>
      <div className="lp-role" key={key}>
        <div>
          <h3>{role.title}</h3>
          <p>{role.text}</p>
          <Link to={`/login?demo=${role.demo}`} className="lp-btn primary sm">Essayer le compte {role.label.toLowerCase()} →</Link>
        </div>
        <ul>{role.points.map((p) => <li key={p}><Icon name="check" size={18} />{p}</li>)}</ul>
      </div>
    </div></section>
  )
}

function Tech() {
  return (
    <section className="lp-section alt" id="technique"><div className="lp-wrap">
      <Reveal><div className="lp-head">
        <span className="lp-eyebrow">Sous le capot</span>
        <h2 className="lp-title">Conçu comme un produit, pas comme une démo</h2>
      </div></Reveal>
      <div className="lp-tech">
        <Reveal><div className="lp-tech-card"><h3>Fiabilité et sécurité</h3><ul>
          <li>Droits vérifiés côté serveur, jamais seulement dans l'interface</li>
          <li>Mots de passe hachés, comptes désactivables, session sécurisée</li>
          <li>Pièces jointes validées sur leur contenu réel</li>
          <li>Traitements asynchrones pour les notifications</li>
          <li>API documentée (OpenAPI) et tests automatisés</li>
        </ul></div></Reveal>
        <Reveal delay={100}><div className="lp-tech-card"><h3>Stack moderne</h3>
          <div className="lp-stack">{['Symfony 6', 'API Platform 3', 'PostgreSQL', 'React', 'TypeScript', 'Vite', 'TanStack Query', 'Docker', 'GitLab CI'].map((t) => <span key={t}>{t}</span>)}</div>
          <p style={{ color: '#5b6479', lineHeight: 1.6, marginBottom: 0 }}>Un backend API et un frontend indépendants, prêts à évoluer : multi-entreprises, SLA, suivi du temps passé.</p>
        </div></Reveal>
      </div>
    </div></section>
  )
}

function FinalCta() {
  return (
    <section className="lp-section"><div className="lp-final"><Reveal><div className="lp-final-box">
      <h2>Prêt à ne plus perdre un seul incident&nbsp;?</h2>
      <p>Connectez-vous pour explorer la plateforme avec les comptes de démonstration.</p>
      <Link to="/login" className="lp-btn light">Se connecter →</Link>
    </div></Reveal></div></section>
  )
}

export default function LandingPage() {
  return (
    <div className="lp">
      <Header />
      <main>
        <Hero /><Stats /><Features /><Journey /><Roles /><Tech /><FinalCta />
      </main>
      <footer className="lp-footer"><div className="lp-wrap"><span>© {new Date().getFullYear()} FixFlow — Gestion des incidents et du support technique</span><span>Symfony · React · PostgreSQL</span></div></footer>
    </div>
  )
}