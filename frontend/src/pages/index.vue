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
          :class="['app-toolbar__menu', { 'lt-md': !pantallaCompleta }]"
          @click="toggleDrawer"
        />

        <AppBuscadorGlobal class="app-toolbar__buscar" />

        <q-space />

        <AppNotificaciones />

        <SwitchDarkMode />

        <div class="app-toolbar__sep" />

        <!-- El avatar abre la cuenta: quién soy y cerrar sesión. -->
        <q-btn
          flat
          round
          dense
          class="app-avatar app-avatar--btn"
          :aria-label="`Cuenta de ${userStore.name}`"
        >
          {{ userStore.initials }}
          <q-menu
            anchor="bottom right"
            self="top right"
            :offset="[0, 8]"
            class="app-cuenta"
          >
            <div class="app-cuenta__head">
              <div class="app-avatar">{{ userStore.initials }}</div>
              <div class="app-cuenta__texto">
                <div class="app-cuenta__nombre">{{ userStore.name }}</div>
                <div class="app-cuenta__rol">{{ userStore.roles?.[0] ?? userStore.username }}</div>
              </div>
            </div>
            <q-separator />
            <q-list dense>
              <q-item
                v-close-popup
                clickable
                class="app-cuenta__salir"
                @click="onLogout"
              >
                <q-item-section avatar>
                  <q-icon
                    name="logout"
                    size="18px"
                  />
                </q-item-section>
                <q-item-section>Cerrar sesión</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
      </q-toolbar>
    </q-header>

    <!-- ══ DRAWER ══ -->
    <!-- Sin `bordered`: Quasar lo dibuja con rgba(0,0,0,0.12) —negro, que en
         tema oscuro es invisible— y con la misma especificidad que nuestra
         regla, así que quién gana dependía del orden del bundle. El borde
         lo dibujamos nosotros con el token, que sí cambia de tema. -->
    <!-- `:key`: cambiar `behavior` en caliente deja a QDrawer con su estado
         interno de desktop en "cerrado" y al salir del POS no volvía. Al
         recrearlo arranca limpio con show-if-above. -->
    <q-drawer
      :key="pantallaCompleta ? 'flota' : 'fijo'"
      v-model="drawerOpen"
      :show-if-above="!pantallaCompleta"
      :behavior="pantallaCompleta ? 'mobile' : 'default'"
      :width="248"
      class="app-drawer"
    >
      <!-- Click en un ítem: cierra el menú cuando está encima del contenido,
           aunque sea la misma ruta (el watch de la ruta ahí no se entera). -->
      <div
        class="app-drawer__inner"
        @click="cerrarSiFlota"
      >
        <div class="app-brand">
          <AppBrandMark :size="32" />
          <span class="app-brand__name">FOR KIDS</span>
        </div>

        <!-- Agrupado por tarea del día, en el orden en que se usa: vender,
             después la mercadería, y al final lo que se configura una vez. -->
        <nav class="app-drawer__nav">
          <AppNavItem
            exact
            to="/"
            icon="dashboard"
            label="Dashboard"
          />
        </nav>

        <template v-if="['ventas.store', 'cajas.actual', 'pedidos.index', 'clientes.index', 'reportes.index'].some((p) => userStore.hasPermission(p))">
          <div class="app-drawer__section">Ventas</div>

          <nav class="app-drawer__nav">
            <AppNavItem
              v-if="userStore.hasPermission('ventas.store')"
              to="/pos"
              icon="point_of_sale"
              label="Punto de venta"
            />

            <AppNavItem
              v-if="userStore.hasPermission('cajas.actual')"
              to="/caja"
              icon="account_balance_wallet"
              label="Caja"
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
              v-if="userStore.hasPermission('reportes.index')"
              to="/reportes"
              icon="insights"
              label="Reportes"
            />
          </nav>
        </template>

        <template v-if="['productos.index', 'inventario.index', 'ofertas.index', 'etiquetas.imprimir'].some((p) => userStore.hasPermission(p))">
          <div class="app-drawer__section">Mercadería</div>

          <nav class="app-drawer__nav">
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
              v-if="userStore.hasPermission('ofertas.index')"
              to="/ofertas"
              icon="local_offer"
              label="Ofertas"
            />

            <AppNavItem
              v-if="userStore.hasPermission('etiquetas.imprimir')"
              to="/etiquetas"
              icon="mdi-barcode"
              label="Etiquetas"
            />
          </nav>
        </template>

        <template v-if="['categorias.index', 'colores.index', 'tallas.index'].some((p) => userStore.hasPermission(p))">
          <div class="app-drawer__section">Configuración</div>

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
      </div>
    </q-drawer>

    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user-store'
import AppBrandMark from '@/components/AppBrandMark.vue'
import AppNavItem from '@/components/AppNavItem.vue'
import AppBadge from '@/components/AppBadge.vue'
import AppBuscadorGlobal from '@/components/AppBuscadorGlobal.vue'
import AppNotificaciones from '@/components/AppNotificaciones.vue'
import SwitchDarkMode from '@/components/SwitchDarkMode.vue'
import PedidoService from '@/services/PedidoService'

const $q = useQuasar()
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

// Pantallas que necesitan todo el ancho (el punto de venta) lo piden con
// `definePage({ meta: { pantallaCompleta: true } })`: el menú se esconde al
// entrar y vuelve al salir. Se abre igual con el botón, encima del contenido.
// Va con behavior="mobile" porque `overlay` solo, en desktop, no trae
// backdrop: no se cerraba al tocar afuera.
const pantallaCompleta = computed(() => Boolean(route.meta.pantallaCompleta))
watch(pantallaCompleta, (completa) => {
  drawerOpen.value = completa ? false : $q.screen.gt.sm
})

// El menú flota encima (pantalla completa, o pantalla chica): al elegir un
// ítem se cierra solo.
function cerrarSiFlota (evt) {
  if (!evt.target.closest('a')) return
  if (pantallaCompleta.value || $q.screen.lt.md) drawerOpen.value = false
}

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

.app-toolbar__menu {
  color: var(--app-ink-2);
}

.app-toolbar__sep {
  width: 1px;
  height: 26px;
  background: var(--app-border-subtle);
}

.app-toolbar__buscar {
  flex: 0 1 380px;
  min-width: 0;
}

.app-avatar--btn {
  padding: 0;
  min-height: 34px;
  cursor: pointer;

  &:hover {
    box-shadow: 0 0 0 3px var(--app-brand-soft);
  }
}

// El menú se teleporta fuera del componente: :global para alcanzarlo.
:global(.app-cuenta) {
  min-width: 220px;
  border-radius: 12px;
}

:global(.app-cuenta__head) {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 16px;
}

:global(.app-cuenta__nombre) {
  font-size: 14px;
  font-weight: 600;
  color: var(--app-ink);
}

:global(.app-cuenta__rol) {
  font-size: 12px;
  color: var(--app-ink-2);
}

:global(.app-cuenta__salir) {
  padding: 10px 16px;
  color: var(--q-negative);
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

// :global porque QDrawer tiene inheritAttrs: false y el `class` cae en su
// <aside> interno, no en su raíz: el atributo del scoped nunca llega ahí y
// con `.app-drawer` a secas la regla no matcheaba (no había línea).
:global(.app-drawer) {
  background: var(--app-surface);
  border-right: 1px solid var(--app-border-control);
}

// QDrawer scrollea en un hijo, no en su raíz. Sin fondo transparente acá, el
// hijo taparía la superficie que acabamos de definir arriba.
:global(.app-drawer .q-drawer__content) {
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

</style>
