export type Role = 'ROLE_ADMIN' | 'ROLE_DEV' | 'ROLE_USER'
export type Status = 'new' | 'in_progress' | 'pending' | 'resolved' | 'closed'
export type Priority = 'low' | 'normal' | 'high' | 'critical'
export type Category = 'bug' | 'performance' | 'security' | 'other'

export interface Me { id: number; email: string; fullName: string; roles: Role[] }
export interface UserRef { id: number; fullName: string; email?: string }
export interface UserAccount extends UserRef { email: string; roles: Role[]; active: boolean }

export interface Ticket {
  id: number; reference: string; title: string; description: string
  category: Category; priority: Priority; status: Status
  creator: UserRef; assignee: UserRef | null
  createdAt: string; updatedAt: string; resolvedAt: string | null; archived: boolean
}
export interface Comment { id: number; content: string; author: UserRef; createdAt: string; updatedAt: string | null }
export interface History { id: number; actor: UserRef; field: string; oldValue: string | null; newValue: string | null; createdAt: string }
export interface Attachment { id: number; name: string; mimeType: string; size: number; uploader: UserRef; createdAt: string }
export interface Notif { id: number; message: string; read: boolean; ticket: string; createdAt: string }

export interface Page<T> { items: T[]; total: number }

export interface TicketSummary { id: number; reference: string; title: string; status: Status; priority: Priority; updatedAt: string }
export interface Dashboard {
  total: number; open: number; inProgress: number; pending: number; critical: number; resolved: number
  byStatus: { key: string; count: number }[]
  byPriority: { key: string; count: number }[]
  avgResolutionHours: number | null
  urgent: TicketSummary[]
  recent: TicketSummary[]
}
