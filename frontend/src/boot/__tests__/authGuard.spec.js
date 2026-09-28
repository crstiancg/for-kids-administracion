import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { Cookies } from 'quasar'

const api = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }))
vi.mock('@/boot/axios', () => ({ api }))

import { authGuard } from '@/boot/authGuard'
import { useUserStore } from '@/stores/user-store'

const USER_OK = { data: { user: { id: 1, name: 'Admin', username: 'admin', email: null }, roles: [], permisos: [] } }

function ruta (path) {
  return { path, fullPath: path }
}

beforeEach(() => {
  setActivePinia(createPinia())
  api.get.mockReset()
  Cookies.remove('token', { path: '/' })
})

describe('authGuard', () => {
  it('sin token, una ruta privada manda al login recordando a dónde iba', async () => {
    expect(await authGuard(ruta('/pedidos'))).toEqual({ path: '/login', query: { redirectTo: '/pedidos' } })
  })

  it('sin token, el login es accesible', async () => {
    expect(await authGuard(ruta('/login'))).toBeUndefined()
  })

  it('con token y sin usuario cargado, lo trae una vez y deja pasar', async () => {
    Cookies.set('token', 'Bearer tok', { path: '/' })
    api.get.mockResolvedValue(USER_OK)

    expect(await authGuard(ruta('/'))).toBeUndefined()
    expect(await authGuard(ruta('/pedidos'))).toBeUndefined()

    expect(api.get).toHaveBeenCalledTimes(1)
    expect(useUserStore().name).toBe('Admin')
  })

  it('con un token que el backend ya no acepta, lo borra y manda al login', async () => {
    Cookies.set('token', 'Bearer vencido', { path: '/' })
    api.get.mockRejectedValue(Object.assign(new Error('401'), { response: { status: 401 } }))

    expect(await authGuard(ruta('/'))).toEqual({ path: '/login', query: { redirectTo: '/' } })
    expect(Cookies.get('token')).toBeFalsy()
  })

  it('con sesión válida, ir al login te devuelve al inicio', async () => {
    Cookies.set('token', 'Bearer tok', { path: '/' })
    api.get.mockResolvedValue(USER_OK)

    expect(await authGuard(ruta('/login'))).toEqual({ path: '/' })
  })
})
