import type { Page } from '../types'

// const API_BASE_URL = (import.meta.env.VITE_API_URL ?? '').replace(/\/$/, '')
const API_BASE_URL = ''
export class ApiError extends Error {
  constructor(public status: number, message: string) {
    super(message)
  }
}

/** Extrait un message lisible d'une réponse d'erreur (API Platform / Symfony / Security). */
function errorMessage(body: any, fallback: string): string {
  if (Array.isArray(body?.violations) && body.violations.length) {
    return body.violations.map((v: { message: string }) => v.message).join(' ')
  }
  return body?.detail ?? body?.['hydra:description'] ?? body?.error ?? body?.message ?? fallback
}

export async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
  const isForm = init.body instanceof FormData
  const headers: Record<string, string> = { Accept: 'application/ld+json' }
  // Pour un FormData, le navigateur ajoute lui-même le Content-Type (avec le « boundary »).
  if (!isForm) headers['Content-Type'] = init.method === 'PATCH' ? 'application/merge-patch+json' : 'application/json'

  const res = await fetch(`${API_BASE_URL}${path}`, {
    credentials: 'same-origin',
    ...init,
    headers,
  })

  if (!res.ok) {
    const body = await res.json().catch(() => ({}))
    throw new ApiError(res.status, errorMessage(body, res.statusText))
  }
  return res.status === 204 ? (undefined as T) : res.json()
}

export async function apiList<T>(path: string): Promise<Page<T>> {
  const d = await api<any>(path)
  return { items: d['hydra:member'] ?? [], total: d['hydra:totalItems'] ?? 0 }
}

export const post = <T,>(p: string, body: unknown) => api<T>(p, { method: 'POST', body: JSON.stringify(body) })
export const patch = <T,>(p: string, body: unknown) => api<T>(p, { method: 'PATCH', body: JSON.stringify(body) })
export const del = (p: string) => api<void>(p, { method: 'DELETE' })
export const upload = <T,>(p: string, file: File) => {
  const form = new FormData()
  form.append('file', file)
  return api<T>(p, { method: 'POST', body: form })
}
