import { PRIORITY_LABEL, STATUS_LABEL } from '../lib/labels'
import type { Priority, Status } from '../types'

export const StatusBadge = ({ status }: { status: Status }) => <span className={`badge ${status}`}>{STATUS_LABEL[status]}</span>
export const PriorityBadge = ({ priority }: { priority: Priority }) => <span className={`badge ${priority}`}>{PRIORITY_LABEL[priority]}</span>
