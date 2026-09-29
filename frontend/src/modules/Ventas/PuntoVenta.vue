<template>
  <div :class="['pos', { 'pos--sinFiltros': !filtrosVisibles }]">
    <!-- Fondo para cerrar los paneles deslizables en pantallas chicas. -->
    <div
      v-if="esMovil && (filtrosAbiertos || carritoAbierto)"
      class="pos__backdrop"
      @click="filtrosAbiertos = false; carritoAbierto = false"
    />

    <!-- ── Filtros ── -->
    <aside
      v-show="filtrosVisibles"
      :class="['pos__filtros', { 'pos__filtros--abierto': filtrosAbiertos }]"
    >
      <CatalogoFiltros v-model="filtros" />
    </aside>

    <!-- ── Catálogo ── -->
    <section class="pos__principal">
      <div class="pos__toolbar">
        <button
          type="button"
          class="pos__icono"
          :aria-label="filtrosVisibles ? 'Ocultar filtros' : 'Mostrar filtros'"
          @click="alternarFiltros"
        >
          <q-icon
            name="tune"
            size="18px"
          />
        </button>

        <div class="pos__titulo">
          <q-icon
            name="point_of_sale"
            size="18px"
          />
          <span>Punto de venta</span>
        </div>

        <div class="pos__buscar">
          <q-icon
            name="search"
            size="16px"
          />
          <input
            ref="buscarRef"
            v-model="busqueda"
            type="search"
            placeholder="Buscar por nombre o SKU…"
            aria-label="Buscar en el catálogo"
          >
          <kbd v-if="!busqueda">Ctrl K</kbd>
        </div>

        <!-- El lector siempre está escuchando: esto sólo lo hace visible. -->
        <div
          class="pos__lector"
          :title="`Lector de códigos activo${ultimoCodigo ? `: último ${ultimoCodigo}` : ''}`"
        >
          <q-icon
            name="qr_code_scanner"
            size="16px"
          />
          <span class="pos__lectorPunto" />
        </div>

        <select
          v-model="orden"
          class="pos__orden"
          aria-label="Ordenar catálogo"
        >
          <option value="vendidos">
            Más vendidos
          </option>
          <option value="nombre">
            A → Z
          </option>
          <option value="precio">
            Precio ↑
          </option>
          <option value="-precio">
            Precio ↓
          </option>
          <option value="stock">
            Más stock
          </option>
        </select>

        <div
          class="pos__vistas"
          role="group"
          aria-label="Vista del catálogo"
        >
          <button
            type="button"
            :class="{ activo: vista === 'grid' }"
            aria-label="Grilla"
            @click="vista = 'grid'"
          >
            <q-icon
              name="grid_view"
              size="16px"
            />
          </button>
          <button
            type="button"
            :class="{ activo: vista === 'list' }"
            aria-label="Lista"
            @click="vista = 'list'"
          >
            <q-icon
              name="view_list"
              size="16px"
            />
          </button>
        </div>

        <button
          v-if="esMovil"
          type="button"
          class="pos__icono pos__verCarrito"
          aria-label="Ver carrito"
          @click="carritoAbierto = true"
        >
          <q-icon
            name="shopping_cart"
            size="18px"
          />
          <span v-if="pos.unidades">{{ pos.unidades }}</span>
        </button>
      </div>

      <div class="pos__catalogo">
        <CatalogoGrid
          ref="catalogoRef"
          :filtros="filtros"
          :search="busquedaAplicada"
          :orden="orden"
          :vista="vista"
          @elegir="elegirProducto"
        />
      </div>

      <div class="pos__atajos">
        <span><kbd>Ctrl K</kbd> buscar</span>
        <span><kbd>F4</kbd> cliente</span>
        <span><kbd>+</kbd> <kbd>−</kbd> <kbd>Supr</kbd> línea</span>
        <span><kbd>F9</kbd> cobrar</span>
      </div>
    </section>

    <!-- ── Carrito ── -->
    <div :class="['pos__carrito', { 'pos__carrito--abierto': carritoAbierto }]">
      <CarritoPanel
        ref="carritoRef"
        :flash-id="flashId"
        :flash-tick="flashTick"
        @vendido="vendido"
      />
    </div>

    <!-- ── Talla × color ── -->
    <AppDialog
      v-model="selectorDialog"
      title="Elegí talla y color"
      size="lg"
    >
      <!-- key: cada producto arranca con su propia foto y color. -->
      <SelectorVariante
        v-if="productoElegido"
        :key="productoElegido.id"
        :producto="productoElegido"
      />
      <template #actions>
        <AppButton
          variant="primary"
          label="Listo"
          @click="selectorDialog = false"
        />
      </template>
    </AppDialog>

    <!-- ── Venta hecha: ticket ── -->
    <AppDialog
      v-model="ticketDialog"
      title="Venta registrada"
      persistent
    >
      <div
        v-if="venta"
        class="pos__ticket"
      >
        <TicketVenta :pedido="venta" />
      </div>
      <q-toggle
        v-model="autoImprimir"
        label="Imprimir automáticamente al cobrar"
        class="pos__auto"
      />
      <template #actions>
        <AppButton
          variant="secondary"
          label="Imprimir ticket"
          icon="print"
          @click="impresionRef.imprimir(venta)"
        />
        <AppButton
          variant="primary"
          label="Nueva venta (Enter)"
          icon="add_shopping_cart"
          @click="nuevaVenta"
        />
      </template>
    </AppDialog>

    <ImpresionTicket ref="impresionRef" />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'
import { useLectorCodigo } from '@/composables/useLectorCodigo'
import InventarioService from '@/services/InventarioService'
import { lineaDesdeCatalogo, lineaDesdeEscaner, usePosStore } from '@/stores/pos-store'
import { beepError, beepOk } from '@/utils/sonido'
import CarritoPanel from './CarritoPanel.vue'
import CatalogoFiltros from './CatalogoFiltros.vue'
import CatalogoGrid from './CatalogoGrid.vue'
import ImpresionTicket from './ImpresionTicket.vue'
import SelectorVariante from './SelectorVariante.vue'
import TicketVenta from './TicketVenta.vue'

const $q = useQuasar()
const pos = usePosStore()

const buscarRef = ref()
const catalogoRef = ref()
const carritoRef = ref()
const impresionRef = ref()

// ── Layout responsivo (como sistema-botica: paneles que se deslizan) ──
const esMovil = computed(() => $q.screen.lt.md)
const filtrosAbiertos = ref(false)
const carritoAbierto = ref(false)
const filtrosEnEscritorio = ref(true)
const filtrosVisibles = computed(() => (esMovil.value ? filtrosAbiertos.value : filtrosEnEscritorio.value))

function alternarFiltros () {
  if (esMovil.value) filtrosAbiertos.value = !filtrosAbiertos.value
  else filtrosEnEscritorio.value = !filtrosEnEscritorio.value
}

// ── Catálogo ──
const filtros = ref({ categoria_id: null, talla_id: null, color_id: null, con_stock: true })
const orden = ref('vendidos')
const vista = ref('grid')
const busqueda = ref('')
const busquedaAplicada = ref('')
let busquedaTimer
watch(busqueda, (valor) => {
  clearTimeout(busquedaTimer)
  busquedaTimer = setTimeout(() => { busquedaAplicada.value = valor.trim() }, 350)
})

// ── Agregar al carrito ──
// Destello de la línea recién sumada (flashTick re-dispara aunque sea la misma).
const flashId = ref(null)
const flashTick = ref(false)
let flashTimer

function sumado (varianteId) {
  beepOk()
  flashId.value = varianteId
  flashTick.value = true
  clearTimeout(flashTimer)
  flashTimer = setTimeout(() => { flashTick.value = false }, 400)
}

const productoElegido = ref(null)
const selectorDialog = ref(false)

// Un producto con una sola variante vendible entra directo; si tiene varias,
// se elige talla × color.
function elegirProducto (producto) {
  const vendibles = producto.variantes.filter((v) => v.stock > pos.cantidadDeVariante(v.id))
  if (producto.variantes.length === 1 && vendibles.length === 1) {
    const variante = vendibles[0]
    if (pos.agregar(lineaDesdeCatalogo(producto, variante)) === 'ok') sumado(variante.id)
    else beepError()
    return
  }
  productoElegido.value = producto
  selectorDialog.value = true
}

// ── Lector de código de barras (en toda la pantalla) ──
const ultimoCodigo = ref('')

async function escaneado (codigo) {
  const sku = codigo.trim().toUpperCase()
  ultimoCodigo.value = sku

  // Lo que ya está en el carrito suma uno sin consultar.
  const enCarrito = pos.items.find((i) => i.sku === sku)
  if (enCarrito) {
    if (pos.cambiarCantidad(enCarrito.variante_id, 1) === 'ok') {
      pos.seleccionado = enCarrito.variante_id
      sumado(enCarrito.variante_id)
    } else {
      beepError()
      $q.notify({ type: 'warning', message: `${enCarrito.nombre}: no hay más stock.`, position: 'top', timeout: 2000 })
    }
    return
  }

  try {
    const { data } = await InventarioService.variantes({ params: { sku, rowsPerPage: 1 } })
    if (!data.length) {
      beepError()
      $q.notify({ type: 'negative', message: `No existe un producto activo con el código ${sku}.`, position: 'top', timeout: 2500 })
      return
    }
    if (pos.agregar(lineaDesdeEscaner(data[0])) === 'ok') {
      sumado(data[0].id)
    } else {
      beepError()
      $q.notify({ type: 'warning', message: `${data[0].producto?.nombre ?? sku}: sin stock.`, position: 'top', timeout: 2000 })
    }
  } catch {
    beepError()
  }
}

// Con el ticket abierto no se escanea (se está cerrando la venta anterior).
useLectorCodigo(escaneado, { habilitado: () => !ticketDialog.value })

// ── Venta hecha ──
const venta = ref(null)
const ticketDialog = ref(false)

// Preferencia de esta PC (la de la ticketera): se recuerda en el navegador.
const autoImprimir = ref(leerPreferencia())
watch(autoImprimir, (valor) => {
  try { localStorage.setItem('pos.autoImprimir', valor ? '1' : '0') } catch { /* sin storage */ }
})
function leerPreferencia () {
  try { return localStorage.getItem('pos.autoImprimir') === '1' } catch { return false }
}

function vendido (pedido) {
  venta.value = pedido
  ticketDialog.value = true
  carritoAbierto.value = false
  if (autoImprimir.value) impresionRef.value.imprimir(pedido)
  // El stock cambió: el catálogo se refresca.
  catalogoRef.value?.refrescar()
}

function nuevaVenta () {
  ticketDialog.value = false
  venta.value = null
  buscarRef.value?.blur()
}

// ── Atajos de teclado ──
function enCampo (el) {
  return el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement || el instanceof HTMLSelectElement
}

function atajos (evento) {
  if (ticketDialog.value) {
    if (evento.key === 'Enter') {
      evento.preventDefault()
      nuevaVenta()
    }
    return
  }

  if ((evento.ctrlKey || evento.metaKey) && evento.key.toLowerCase() === 'k') {
    evento.preventDefault()
    buscarRef.value?.focus()
    return
  }

  switch (evento.key) {
    case 'F2':
      evento.preventDefault()
      buscarRef.value?.focus()
      return
    case 'F4':
      evento.preventDefault()
      carritoRef.value?.enfocarCliente()
      return
    case 'F9':
      evento.preventDefault()
      carritoRef.value?.cobrar()
      return
    case 'Escape':
      if (document.activeElement === buscarRef.value) buscarRef.value.blur()
      return
  }

  // + / − / Supr sobre la línea activa, sólo si no se está escribiendo.
  if (enCampo(document.activeElement) || selectorDialog.value || !pos.seleccionado) return
  if (evento.key === '+') {
    evento.preventDefault()
    if (pos.cambiarCantidad(pos.seleccionado, 1) === 'ok') sumado(pos.seleccionado)
    else beepError()
  } else if (evento.key === '-') {
    evento.preventDefault()
    pos.cambiarCantidad(pos.seleccionado, -1)
  } else if (evento.key === 'Delete') {
    evento.preventDefault()
    pos.quitar(pos.seleccionado)
  }
}

// ── Borrador: sobrevive a una recarga ──
watch(() => [pos.items, pos.cliente, pos.descuento, pos.espera], () => pos.persistir(), { deep: true })

onMounted(() => {
  pos.hidratar()
  window.addEventListener('keydown', atajos)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', atajos)
})
</script>

<style lang="scss" scoped>
// Ocupa todo el alto disponible bajo la barra de la app: el carrito no se
// desplaza con la página, sólo su lista de líneas.
.pos {
  display: grid;
  grid-template-columns: 230px minmax(0, 1fr) 380px;
  height: calc(100vh - var(--app-header-height, 65px));
  min-height: 560px;
  background: var(--app-bg, transparent);

  &--sinFiltros {
    grid-template-columns: minmax(0, 1fr) 380px;
  }

  @media (max-width: 1023px) {
    grid-template-columns: minmax(0, 1fr);
    height: auto;
    min-height: calc(100vh - var(--app-header-height, 65px));
  }
}

.pos__filtros {
  overflow-y: auto;
  padding: 16px;
  border-right: 1px solid var(--app-border-subtle);
  background: var(--app-surface);

  @media (max-width: 1023px) {
    position: fixed;
    inset: 0 auto 0 0;
    z-index: 3000;
    width: 280px;
    transform: translateX(-100%);
    transition: transform 0.2s ease;

    &--abierto {
      transform: none;
    }
  }
}

.pos__principal {
  display: flex;
  flex-direction: column;
  min-height: 0;
}

.pos__toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--app-border-subtle);
  background: var(--app-surface);
}

.pos__icono {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border: 1px solid var(--app-border-control);
  border-radius: 10px;
  background: var(--app-surface);
  color: var(--app-ink);
  cursor: pointer;

  span {
    position: absolute;
    top: -6px;
    right: -6px;
    min-width: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: $primary;
    font-size: 11px;
    font-weight: 700;
    line-height: 18px;
    color: #FFFFFF;
  }
}

.pos__titulo {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 15px;
  font-weight: 700;
  white-space: nowrap;
  color: var(--app-ink);

  .q-icon {
    color: $primary;
  }

  @media (max-width: 1279px) {
    display: none;
  }
}

.pos__buscar {
  display: flex;
  flex: 1;
  align-items: center;
  gap: 8px;
  min-width: 0;
  padding: 0 12px;
  border: 1px solid var(--app-border-control);
  border-radius: 10px;
  background: var(--app-surface);
  color: var(--app-ink-2);

  &:focus-within {
    border-color: $primary;
    box-shadow: 0 0 0 3px rgba($primary, 0.12);
  }

  input {
    flex: 1;
    min-width: 0;
    height: 36px;
    border: 0;
    outline: none;
    background: none;
    font-size: 14px;
    color: var(--app-ink);
  }

  kbd {
    padding: 1px 6px;
    border: 1px solid var(--app-border-control);
    border-radius: 4px;
    font-family: $font-mono;
    font-size: 10.5px;
  }
}

.pos__lector {
  position: relative;
  display: flex;
  align-items: center;
  padding: 0 6px;
  color: var(--app-ink-2);
}

// "Escuchando": un punto verde que late.
.pos__lectorPunto {
  position: absolute;
  top: -2px;
  right: 2px;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #16A34A;
  animation: latido 2s ease-in-out infinite;
}

@keyframes latido {
  50% { opacity: 0.35; }
}

.pos__orden {
  height: 36px;
  padding: 0 8px;
  border: 1px solid var(--app-border-control);
  border-radius: 10px;
  background: var(--app-surface);
  font-size: 13px;
  color: var(--app-ink);

  @media (max-width: 599px) {
    display: none;
  }
}

.pos__vistas {
  display: flex;
  overflow: hidden;
  border: 1px solid var(--app-border-control);
  border-radius: 10px;

  button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border: 0;
    background: var(--app-surface);
    color: var(--app-ink-2);
    cursor: pointer;

    &.activo {
      background: rgba($primary, 0.1);
      color: $primary;
    }
  }

  @media (max-width: 599px) {
    display: none;
  }
}

.pos__catalogo {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
}

.pos__atajos {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  padding: 8px 16px;
  border-top: 1px solid var(--app-border-subtle);
  background: var(--app-surface);
  font-size: 11.5px;
  color: var(--app-ink-2);

  kbd {
    padding: 0 5px;
    border: 1px solid var(--app-border-control);
    border-radius: 4px;
    font-family: $font-mono;
    font-size: 10.5px;
  }

  @media (max-width: 1023px) {
    display: none;
  }
}

.pos__carrito {
  min-height: 0;

  @media (max-width: 1023px) {
    position: fixed;
    inset: 0 0 0 auto;
    z-index: 3000;
    width: min(420px, 100vw);
    transform: translateX(100%);
    transition: transform 0.2s ease;

    &--abierto {
      transform: none;
    }
  }
}

.pos__backdrop {
  position: fixed;
  inset: 0;
  z-index: 2999;
  background: rgba(0, 0, 0, 0.35);
}

.pos__ticket {
  max-height: 55vh;
  overflow-y: auto;
  padding: 8px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 10px;
  background: #FFFFFF;
}

.pos__auto {
  margin-top: 8px;
  font-size: 13px;
}
</style>
