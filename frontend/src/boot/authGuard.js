import { defineBoot } from '#q-app'
import { Cookies } from 'quasar'
import { useUserStore } from '@/stores/user-store'

/**
 * Las rutas salen de los archivos de src/pages (vue-router/auto-routes), así
 * que en vez de marcar cada página con meta.requiresAuth como en la
 * referencia, todo es privado salvo esta lista.
 */
const PUBLIC_PATHS = ['/login']

export async function authGuard (to) {
  const hasToken = Boolean(Cookies.get('token'))
  const isPublic = PUBLIC_PATHS.includes(to.path)

  if (!hasToken) {
    return isPublic ? undefined : { path: '/login', query: { redirectTo: to.fullPath } }
  }

  const userStore = useUserStore()
  if (!userStore.id) {
    try {
      await userStore.getUser()
    } catch {
      Cookies.remove('token', { path: '/' })
      return isPublic ? undefined : { path: '/login', query: { redirectTo: to.fullPath } }
    }
  }

  if (to.path === '/login') return { path: '/' }
}

export default defineBoot(({ router }) => {
  router.beforeEach(authGuard)
})
