import type { Category, History, Priority, Role, Status } from '../types'

export const STATUSES: Status[] = ['new', 'in_progress', 'pending', 'resolved', 'closed']
export const PRIORITIES: Priority[] = ['low', 'normal', 'high', 'critical']
export const CATEGORIES: Category[] = ['bug', 'performance', 'security', 'other']

export const STATUS_LABEL: Record<Status, string> = {
  new: 'Nouveau', in_progress: 'En cours', pending: 'En attente', resolved: 'Résolu', closed: 'Fermé',
}
export const PRIORITY_LABEL: Record<Priority, string> = {
  low: 'Faible', normal: 'Normale', high: 'Élevée', critical: 'Critique',
}
export const CATEGORY_LABEL: Record<Category, string> = {
  bug: 'Bug', performance: 'Performance', security: 'Sécurité', other: 'Autre',
}
export const ROLE_LABEL: Record<Role, string> = {
  ROLE_ADMIN: 'Administrateur', ROLE_DEV: 'Développeur', ROLE_USER: 'Utilisateur',
}
const FIELD_LABEL: Record<string, string> = {
  status: 'le statut', priority: 'la priorité', category: 'la catégorie', assignee: "l'assignation",
  title: 'le titre', description: 'la description', archived: "l'archivage",
}

const VALUE_LABELS: Record<string, Record<string, string>> = {
  status: STATUS_LABEL, priority: PRIORITY_LABEL, category: CATEGORY_LABEL,
}

/** Libellé lisible d'une valeur technique (ex. `in_progress` → « En cours »). */
export function valueLabel(field: string, value: string | null): string {
  if (value === null || value === '') return '—'
  return VALUE_LABELS[field]?.[value] ?? value
}

/** Phrase décrivant une ligne d'historique, sans l'auteur ni la date. */
export function describeChange(h: Pick<History, 'field' | 'oldValue' | 'newValue'>): string {
  if (h.field === 'created') return "a créé l'incident"
  const what = FIELD_LABEL[h.field] ?? h.field
  if (h.field === 'description') return `a modifié ${what}`
  return `a modifié ${what} : ${valueLabel(h.field, h.oldValue)} → ${valueLabel(h.field, h.newValue)}`
}

export function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} o`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} Ko`
  return `${(bytes / 1024 / 1024).toFixed(1)} Mo`
}

export const formatDateTime = (iso: string) => new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })
export const formatDate = (iso: string) => new Date(iso).toLocaleDateString('fr-FR')
