import { createContext, useContext, type ReactNode } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { api, post } from '../../services/api'
import type { Me, Role } from '../../types'

interface Auth {
  me: Me | null
  loading: boolean
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
  has: (r: Role) => boolean
}
const Ctx = createContext<Auth>(null!)
export const useAuth = () => useContext(Ctx)

export function AuthProvider({ children }: { children: ReactNode }) {
  const qc = useQueryClient()
  const { data, isLoading } = useQuery({ queryKey: ['me'], retry: false, staleTime: Infinity, queryFn: () => api<Me>('/api/me').catch(() => null) })
  const me = data ?? null
  const value: Auth = {
    me,
    loading: isLoading,
    has: (r) => !!me?.roles.includes(r),
    login: async (email, password) => {
      qc.setQueryData(['me'], await post<Me>('/api/login', { email, password }))
    },
    logout: async () => {
      await fetch('/api/logout', { method: 'POST' }).catch(() => undefined)
      qc.clear()
      qc.setQueryData(['me'], null)
    },
  }
  return <Ctx.Provider value={value}>{children}</Ctx.Provider>
}
