<template>
  <q-layout view="LHh Lpr lFf">
    <!-- ══ TOOLBAR ══ -->
    <q-header class="app-header">
      <q-toolbar class="app-toolbar">
        <q-btn
          flat
          dense
          round
          icon="menu"
          aria-label="Abrir navegación"
          class="app-toolbar__menu lt-md"
          @click="toggleDrawer"
        />

        <div class="app-search">
          <q-icon
            name="search"
            class="app-search__icon"
          />
          <q-input
            v-model="search"
            borderless
            dense
            placeholder="Buscar pedidos, clientes…"
            class="app-search__field"
          />
        </div>

        <q-space />

        <q-btn
          flat
          dense
          round
          icon="notifications_none"
          aria-label="Notificaciones"
          class="app-toolbar__bell"
        >
          <AppBadge
            dot
            floating
            sr-label="Tenés notificaciones sin leer"
          />
        </q-btn>

        <div class="app-toolbar__sep" />

        <div class="app-avatar">{{ userStore.initials }}</div>
      </q-toolbar>
    </q-header>

    <!-- ══ DRAWER ══ -->
    <!-- Sin `bordered`: Quasar lo dibuja con rgba(0,0,0,0.12) —negro, que en
         tema oscuro es invisible— y con la misma especificidad que nuestra
         regla, así que quién gana dependía del orden del bundle. El borde
         lo dibujamos nosotros con el token, que sí cambia de tema. -->
    <q-drawer
      v-model="drawerOpen"
      show-if-above
      :width="248"
      class="app-drawer"
    >
      <div class="app-drawer__inner">
        <div class="app-brand">
          <AppBrandMark :size="32" />
          <span class="app-brand__name">FOR KIDS</span>
        </div>

        <div class="app-drawer__section">General</div>

        <nav class="app-drawer__nav">
          <AppNavItem
            exact
            to="/"
            icon="dashboard"
            label="Dashboard"
          />

          <AppNavItem
            v-if="userStore.hasPermission('pedidos.index')"
            to="/pedidos"
            icon="receipt_long"
            label="Pedidos"
          >
            <!-- Pedidos pendientes reales (antes, un 14 fijo de la maqueta). -->
            <template
              v-if="pendientes"
              #badge
            >
              <AppBadge variant="brand">
                {{ pendientes }}
              </AppBadge>
            </template>
          </AppNavItem>

          <AppNavItem
            v-if="userStore.hasPermission('clientes.index')"
            to="/clientes"
            icon="groups"
            label="Clientes"
          />

          <AppNavItem
            v-if="userStore.hasPermission('cajas.actual')"
            to="/caja"
            icon="point_of_sale"
            label="Caja"
          />

          <AppNavItem
            v-if="userStore.hasPermission('productos.index')"
            to="/productos"
            icon="inventory_2"
            label="Productos"
          />

          <AppNavItem
            v-if="userStore.hasPermission('inventario.index')"
            to="/inventario"
            icon="warehouse"
            label="Inventario"
          />

          <AppNavItem
            to="/second"
            icon="widgets"
            label="Segunda página"
          />
        </nav>

        <template v-if="['categorias.index', 'colores.index', 'tallas.index'].some((p) => userStore.hasPermission(p))">
          <div class="app-drawer__section">Catálogos</div>

          <nav class="app-drawer__nav">
            <AppNavItem
              v-if="userStore.hasPermission('categorias.index')"
              to="/categorias"
              icon="category"
              label="Categorías"
            />
            <AppNavItem
              v-if="userStore.hasPermission('colores.index')"
              to="/colores"
              icon="palette"
              label="Colores"
            />
            <AppNavItem
              v-if="userStore.hasPermission('tallas.index')"
              to="/tallas"
              icon="straighten"
              label="Tallas"
            />
          </nav>
        </template>

        <template v-if="['usuarios.index', 'roles.index', 'permisos.index'].some((p) => userStore.hasPermission(p))">
          <div class="app-drawer__section">Seguridad</div>

          <nav class="app-drawer__nav">
            <AppNavItem
              v-if="userStore.hasPermission('usuarios.index')"
              to="/usuarios"
              icon="group"
              label="Usuarios"
            />

            <AppNavItem
              v-if="userStore.hasPermission('roles.index')"
              to="/roles"
              icon="badge"
              label="Roles"
            />

            <AppNavItem
              v-if="userStore.hasPermission('permisos.index')"
              to="/permisos"
              icon="key"
              label="Permisos"
            />
          </nav>
        </template>

        <div class="app-drawer__user">
          <div class="app-avatar">{{ userStore.initials }}</div>
          <div class="app-drawer__userText">
            <div class="app-drawer__userName">{{ userStore.name }}</div>
            <div class="app-drawer__userRole">{{ userStore.roles?.[0] ?? userStore.username }}</div>
          </div>
          <q-btn
            flat
            dense
            round
            icon="logout"
            aria-label="Cerrar sesión"
            class="app-drawer__logout"
            @click="onLogout"
          />
        </div>
      </div>
    </q-drawer>

    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user-store'
import AppBrandMark from '@/components/AppBrandMark.vue'
import AppNavItem from '@/components/AppNavItem.vue'
import AppBadge from '@/components/AppBadge.vue'
import PedidoService from '@/services/PedidoService'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()

// Pedidos pendientes para el badge del menú. Se recalcula al navegar: basta
// para que se entere al volver de confirmar o cancelar uno.
const pendientes = ref(0)
async function contarPendientes () {
  if (!userStore.hasPermission('pedidos.index')) return
  try {
    const { total } = await PedidoService.getData({ params: { estado: 'pendiente', rowsPerPage: 1 } })
    pendientes.value = total ?? 0
  } catch {
    // Un badge no vale un error en pantalla.
  }
}
watch(() => route.path, contarPendientes, { immediate: true })

async function onLogout () {
  await userStore.logout()
  router.replace('/login')
}

const drawerOpen = ref(false)
const search = ref('')

function toggleDrawer () {
  drawerOpen.value = !drawerOpen.value
}
</script>

<style lang="scss" scoped>
// QHeader viene con fondo $primary por default. Acá eso sería una franja
// roja de 64px cruzando la pantalla, justo lo que la paleta no quiere:
// el rojo es acento, no superficie.
.app-header {
  background: var(--app-surface);
  color: var(--app-ink);
  border-bottom: 1px solid var(--app-border-subtle);
  box-shadow: none;
}

.app-toolbar {
  height: 64px;
  padding: 0 32px;
  gap: 16px;
}

.app-toolbar__menu,
.app-toolbar__bell {
  color: var(--app-ink-2);
}

.app-toolbar__sep {
  width: 1px;
  height: 26px;
  background: var(--app-border-subtle);
}

.app-search {
  display: flex;
  align-items: center;
  gap: 9px;
  width: 340px;
  height: 40px;
  padding: 0 13px;
  border-radius: 9px;
  background: var(--app-page);
  border: 1px solid var(--app-border-subtle);
}

.app-search__icon {
  font-size: 17px;
  color: var(--app-ink-2);
  flex-shrink: 0;
}

.app-search__field {
  flex-grow: 1;
  font-size: 13.5px;
}

.app-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 999px;
  background: var(--app-brand-soft);
  color: var(--app-brand-soft-ink);
  font-size: 12px;
  font-weight: 700;
  flex-shrink: 0;
}

.app-drawer {
  background: var(--app-surface);
  border-right: 1px solid var(--app-border-subtle);
}

// QDrawer scrollea en un hijo, no en su raíz. Sin fondo transparente acá, el
// hijo taparía la superficie que acabamos de definir arriba.
.app-drawer :deep(.q-drawer__content) {
  background: transparent;
}

.app-drawer__inner {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 22px 16px;
}

.app-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 8px 26px;
}

.app-brand__name {
  font-size: 16px;
  font-weight: 700;
  letter-spacing: -0.2px;
  color: var(--app-ink);
}

.app-drawer__section {
  padding: 0 8px 8px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.7px;
  text-transform: uppercase;
  color: var(--app-ink-2);
}

.app-drawer__nav {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

// Una sección que viene después de otra lista necesita aire arriba; la
// primera no, ya la separa la marca.
.app-drawer__nav + .app-drawer__section {
  margin-top: 22px;
}

.app-drawer__user {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: auto;
  padding: 12px;
  border-radius: 10px;
  border: 1px solid var(--app-border-subtle);
}

.app-drawer__userText {
  min-width: 0;
}

.app-drawer__userName {
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.app-drawer__userRole {
  font-size: 11.5px;
  color: var(--app-ink-2);
}

.app-drawer__logout {
  margin-left: auto;
  color: var(--app-ink-2);
}
</style>
