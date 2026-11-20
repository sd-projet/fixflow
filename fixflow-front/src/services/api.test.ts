import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError, api, apiList, patch, upload } from './api'

const mockFetch = (body: unknown, init: { ok?: boolean; status?: number } = {}) => {
  const fn = vi.fn().mockResolvedValue({ ok: init.ok ?? true, status: init.status ?? 200, statusText: 'KO', json: async () => body })
  vi.stubGlobal('fetch', fn)
  return fn
}
afterEach(() => vi.unstubAllGlobals())

describe('apiList', () => {
  it('extrait les membres et le total Hydra', async () => {
    mockFetch({ 'hydra:member': [{ id: 1 }], 'hydra:totalItems': 42 })
    expect(await apiList('/api/tickets')).toEqual({ items: [{ id: 1 }], total: 42 })
  })
})

describe('api', () => {
  it('utilise merge-patch pour PATCH', async () => {
    const fn = mockFetch({})
    await patch('/api/tickets/1', { status: 'closed' })
    expect(fn.mock.calls[0][1].headers['Content-Type']).toBe('application/merge-patch+json')
  })

  it("n'impose pas de Content-Type pour un FormData", async () => {
    const fn = mockFetch({})
    await upload('/api/tickets/1/attachments', new File(['x'], 'a.txt'))
    expect(fn.mock.calls[0][1].headers['Content-Type']).toBeUndefined()
  })

  it('renvoie undefined pour un 204', async () => {
    mockFetch(null, { status: 204 })
    expect(await api('/api/x', { method: 'DELETE' })).toBeUndefined()
  })

  it('transforme les violations de validation en message', async () => {
    mockFetch({ violations: [{ message: 'Trop court.' }, { message: 'Invalide.' }] }, { ok: false, status: 422 })
    await expect(api('/api/x')).rejects.toMatchObject({ status: 422, message: 'Trop court. Invalide.' })
  })

  it("expose le statut HTTP dans ApiError", async () => {
    mockFetch({ error: 'Invalid credentials.' }, { ok: false, status: 401 })

    const err = await api('/api/login').catch((e: unknown) => {
      if (!(e instanceof ApiError)) throw e
      return e
    })

    expect(err).toBeInstanceOf(ApiError)
    expect(err.status).toBe(401)
    expect(err.message).toBe('Invalid credentials.')
  })

})
