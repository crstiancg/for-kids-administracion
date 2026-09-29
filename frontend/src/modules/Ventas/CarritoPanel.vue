<template>
  <aside class="carrito">
    <!-- ── Cabecera + ventas en espera ── -->
    <header class="carrito__cabecera">
      <div>
        <div class="carrito__titulo">
          Venta actual
        </div>
        <div class="carrito__sub">
          {{ pos.unidades }} {{ pos.unidades === 1 ? 'unidad' : 'unidades' }}
        </div>
      </div>
      <div class="carrito__espera">
        <q-btn
          flat
          dense
          no-caps
          size="sm"
          icon="pause_circle"
          label="En espera"
          :disable="!pos.items.length"
          @click="aparcar"
        >
          <q-tooltip>Aparcar esta venta y atender a otro cliente</q-tooltip>
        </q-btn>
        <q-btn
          v-if="pos.espera.length"
          flat
          dense
          no-caps
          size="sm"
          icon="history"
          :label="String(pos.espera.length)"
          class="carrito__esperaLista"
        >
          <q-menu anchor="bottom right" self="top right">
            <q-list style="min-width: 240px">
              <q-item-label header>
                Ventas en espera
              </q-item-label>
              <q-item
                v-for="venta in pos.espera"
                :key="venta.id"
                clickable
                @click="pos.retomar(venta.id)"
              >
                <q-item-section>
                  <q-item-label>{{ venta.cliente?.nombre ?? 'Cliente varios' }}</q-item-label>
                  <q-item-label caption>
                    {{ unidadesDe(venta) }} unid. · {{ horaDe(venta.fecha) }}
                  </q-item-label>
                </q-item-section>
                <q-item-section side>
                  <q-btn
                    flat
                    dense
                    round
                    size="sm"
                    icon="delete_outline"
                    aria-label="Descartar venta en espera"
                    @click.stop="pos.descartarEspera(venta.id)"
                  />
                </q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
      </div>
    </header>

    <!-- ── Líneas ── -->
    <div class="carrito__lineas">
      <div
        v-if="!pos.items.length"
        class="carrito__vacio"
      >
        <q-icon
          name="qr_code_scanner"
          size="34px"
        />
        <div class="carrito__vacioTitulo">
          Carrito vacío
        </div>
        <div>Escaneá un código o elegí del catálogo.</div>
      </div>

      <div
        v-for="(item, i) in pos.items"
        :key="item.variante_id"
        :class="['linea', {
          'linea--activa': pos.seleccionado === item.variante_id,
          'linea--flash': flashId === item.variante_id && flashTick
        }]"
        @click="pos.seleccionado = item.variante_id"
      >
        <div
          class="linea__thumb"
          :style="item.miniatura_url ? { backgroundImage: `url(${item.miniatura_url})` } : {}"
        >
          <q-icon
            v-if="!item.miniatura_url"
            name="checkroom"
            size="16px"
          />
        </div>

        <div class="linea__cuerpo">
          <div class="linea__nombre">
            {{ item.nombre }}
          </div>
          <div class="linea__detalle">
            <span class="linea__talla">T{{ item.talla }}</span>
            <span
              v-if="item.color"
              class="linea__swatch"
              :style="{ background: item.color.hexadecimal }"
              :title="item.color.nombre"
            />
            {{ item.color?.nombre }} · <span class="text-mono">{{ item.sku }}</span>
          </div>

          <div class="linea__controles">
            <div class="linea__cantidad">
              <button
                type="button"
                :aria-label="`Uno menos de ${item.sku}`"
                @click.stop="cambiar(item, -1)"
              >
                <q-icon
                  name="remove"
                  size="12px"
                />
              </button>
              <span class="text-mono">{{ item.cantidad }}</span>
              <button
                type="button"
                :aria-label="`Uno más de ${item.sku}`"
                :disabled="item.cantidad >= item.stock"
                @click.stop="cambiar(item, 1)"
              >
                <q-icon
                  name="add"
                  size="12px"
                />
              </button>
            </div>
            <input
              v-model="item.precio_unitario"
              type="number"
              min="0"
              step="0.01"
              class="linea__precio text-mono"
              :aria-label="`Precio unitario de ${item.sku}`"
              @click.stop
            >
            <span class="linea__total text-mono">{{ formatearPrecio(item.cantidad * Number(item.precio_unitario || 0)) }}</span>
          </div>

          <p
            v-if="errorItem(i)"
            class="carrito__error"
          >
            {{ errorItem(i) }}
          </p>
        </div>

        <button
          type="button"
          class="linea__quitar"
          :aria-label="`Quitar ${item.sku}`"
          @click.stop="pos.quitar(item.variante_id)"
        >
          <q-icon
            name="close"
            size="13px"
          />
        </button>
      </div>
    </div>

    <!-- ── Cobro ── -->
    <footer class="carrito__pie">
      <div class="carrito__cliente">
        <span
          :id="`${uid}-cliente`"
          class="sr-only"
        >Cliente (F4)</span>
        <BuscadorCliente
          ref="clienteRef"
          v-model="pos.cliente"
          :aria-labelledby="`${uid}-cliente`"
        />
      </div>

      <div class="carrito__fila">
        <span>Subtotal</span>
        <span class="text-mono">{{ formatearPrecio(pos.subtotal) }}</span>
      </div>
      <div class="carrito__fila">
        <label :for="`${uid}-descuento`">Descuento</label>
        <input
          :id="`${uid}-descuento`"
          v-model="pos.descuento"
          type="number"
          min="0"
          step="0.01"
          placeholder="0.00"
          class="carrito__descuento text-mono"
        >
      </div>
      <p
        v-if="errores['venta.descuento']"
        class="carrito__error"
      >
        {{ errores['venta.descuento'][0] }}
      </p>
      <div class="carrito__total">
        <span>Total</span>
        <span class="text-mono">{{ formatearPrecio(pos.total) }}</span>
      </div>

      <!-- Pagos: uno o mixto -->
      <div
        v-for="(pago, j) in pos.pagos"
        :key="pago.uid"
        class="pago"
      >
        <div
          class="pago__metodos"
          role="radiogroup"
          :aria-label="`Método del pago ${j + 1}`"
        >
          <button
            v-for="metodo in METODOS"
            :key="metodo.value"
            type="button"
            role="radio"
            :aria-checked="String(pago.metodo === metodo.value)"
            :class="['pago__metodo', { 'pago__metodo--activo': pago.metodo === metodo.value }]"
            @click="pago.metodo = metodo.value"
          >
            <q-icon
              :name="metodo.icon"
              size="16px"
            />
            <span>{{ metodo.label }}</span>
          </button>
        </div>

        <div class="pago__campos">
          <label class="pago__campo">
            <span>{{ pos.pagos.length > 1 ? 'Monto' : 'Cobra' }}</span>
            <input
              v-model="pago.monto"
              type="number"
              min="0.01"
              step="0.01"
              class="text-mono"
              @input="pago.montoManual = true"
            >
          </label>
          <label
            v-if="pago.metodo === 'efectivo'"
            class="pago__campo"
          >
            <span>Recibe</span>
            <input
              v-model="pago.recibido"
              type="number"
              min="0"
              step="0.01"
              :placeholder="pago.monto"
              class="text-mono"
            >
          </label>
          <label
            v-else
            class="pago__campo"
          >
            <span>{{ CON_OPERACION.includes(pago.metodo) ? 'N° operación' : 'Voucher' }}</span>
            <input
              v-model="pago.referencia"
              type="text"
              maxlength="40"
            >
          </label>
          <button
            v-if="pos.pagos.length > 1"
            type="button"
            class="pago__quitar"
            :aria-label="`Quitar pago ${j + 1}`"
            @click="pos.quitarPago(j)"
          >
            <q-icon
              name="close"
              size="13px"
            />
          </button>
        </div>

        <p
          v-if="vueltoDe(pago) !== null"
          :class="['pago__vuelto', { 'pago__vuelto--falta': vueltoDe(pago) < 0 }]"
          role="status"
        >
          {{ vueltoDe(pago) >= 0 ? 'Vuelto' : 'Faltan' }}
          <strong class="text-mono">{{ formatearPrecio(Math.abs(vueltoDe(pago))) }}</strong>
        </p>
        <p
          v-for="campo in ['metodo', 'monto', 'recibido', 'referencia']"
          v-show="errorPago(j, campo)"
          :key="campo"
          class="carrito__error"
        >
          {{ errorPago(j, campo) }}
        </p>
      </div>

      <div class="carrito__pagosPie">
        <button
          v-if="pos.pagos.length < 5"
          type="button"
          class="carrito__link"
          @click="pos.agregarPago()"
        >
          + Pago mixto
        </button>
        <span
          v-if="Math.abs(pos.restante) >= 0.005"
          :class="['carrito__restante', { 'carrito__restante--sobra': pos.restante < 0 }]"
        >
          {{ pos.restante > 0 ? `Falta asignar ${formatearPrecio(pos.restante)}` : `Sobran ${formatearPrecio(-pos.restante)}` }}
        </span>
      </div>
      <p
        v-if="errores['venta.pagos']"
        class="carrito__error"
      >
        {{ errores['venta.pagos'][0] }}
      </p>

      <div
        v-if="!cargandoCaja && !caja"
        class="carrito__caja"
        role="alert"
      >
        <q-icon
          name="lock"
          size="16px"
        />
        <span>Caja cerrada</span>
        <button
          v-if="userStore.hasPermission('cajas.abrir')"
          type="button"
          @click="abrirCajaDialog = true"
        >
          Abrir
        </button>
      </div>

      <button
        type="button"
        class="carrito__cobrar"
        :disabled="!puedeCobrar"
        @click="cobrar"
      >
        <q-spinner
          v-if="procesando"
          size="18px"
        />
        <template v-else>
          Cobrar · {{ formatearPrecio(pos.total) }}
          <kbd>F9</kbd>
        </template>
      </button>
    </footer>

    <AppDialog
      v-model="abrirCajaDialog"
      title="Abrir caja"
    >
      <AbrirCajaForm
        v-if="abrirCajaDialog"
        @save="cajaAbierta"
      />
    </AppDialog>
  </aside>
</template>

<script setup>
import { computed, onMounted, ref, useId, watch } from 'vue'
import { useQuasar } from 'quasar'
import AppDialog from '@/components/AppDialog.vue'
import AbrirCajaForm from '@/modules/Caja/AbrirCajaForm.vue'
import { CON_OPERACION, METODOS } from '@/modules/Caja/constantes'
import BuscadorCliente from '@/modules/Pedidos/BuscadorCliente.vue'
import CajaService from '@/services/CajaService'
import VentaService from '@/services/VentaService'
import { usePosStore } from '@/stores/pos-store'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'
import { beepError } from '@/utils/sonido'

/**
 * Panel derecho del punto de venta: el carrito, el cliente, los pagos y el
 * cobro. El estado vive en el store (sobrevive a una recarga).
 */
defineProps({
  // Línea recién sumada (destello). `flashTick` alterna para re-disparar.
  flashId: {
    type: Number,
    default: null
  },
  flashTick: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['vendido'])

const $q = useQuasar()
const pos = usePosStore()
const userStore = useUserStore()
const uid = `carrito-${useId()}`
const clienteRef = ref()

// ── Caja ──
const caja = ref(null)
const cargandoCaja = ref(true)
const abrirCajaDialog = ref(false)

async function cargarCaja () {
  try {
    caja.value = await CajaService.actual()
  } finally {
    cargandoCaja.value = false
  }
}

function cajaAbierta (nueva) {
  caja.value = nueva
  abrirCajaDialog.value = false
  $q.notify({ type: 'positive', message: 'Caja abierta.', position: 'top-right', timeout: 1500 })
}

onMounted(cargarCaja)

// ── Carrito ──
function cambiar (item, delta) {
  if (pos.cambiarCantidad(item.variante_id, delta) === 'sin-stock') beepError()
}

function aparcar () {
  if (pos.aparcar()) {
    $q.notify({ type: 'info', message: 'Venta en espera. Retomala desde el reloj de arriba.', position: 'top-right', timeout: 2000 })
  } else {
    $q.notify({ type: 'warning', message: 'Máximo 5 ventas en espera.', position: 'top-right', timeout: 2000 })
  }
}

function unidadesDe (venta) {
  return venta.items.reduce((s, i) => s + i.cantidad, 0)
}

const formatoHora = new Intl.DateTimeFormat('es-PE', { hour: '2-digit', minute: '2-digit' })
function horaDe (iso) {
  return formatoHora.format(new Date(iso))
}

// Con un solo pago que no se tocó, el monto sigue al total.
watch(() => pos.total, () => pos.sincronizarPagoUnico(), { immediate: true })

function vueltoDe (pago) {
  if (pago.metodo !== 'efectivo' || pago.recibido === '' || pago.recibido === null) return null
  return Math.round((Number(pago.recibido) - Number(pago.monto)) * 100) / 100
}

// ── Cobro ──
const errores = ref({})
const procesando = ref(false)

// Cualquier cambio del carrito invalida los errores del cobro anterior.
watch(() => [pos.items.length, pos.total], () => { errores.value = {} })

function errorItem (i) {
  return ['variante_id', 'cantidad', 'precio_unitario']
    .map((c) => errores.value[`venta.items.${i}.${c}`]?.[0])
    .find(Boolean)
}

function errorPago (j, campo) {
  return errores.value[`venta.pagos.${j}.${campo}`]?.[0]
}

const puedeCobrar = computed(() =>
  Boolean(caja.value) && pos.items.length > 0 && pos.total > 0 && Math.abs(pos.restante) < 0.005 && !procesando.value)

async function cobrar () {
  if (!puedeCobrar.value) {
    if (pos.items.length) beepError()
    return
  }
  procesando.value = true
  errores.value = {}

  try {
    const pedido = await VentaService.registrar({
      cliente_id: pos.cliente?.id ?? null,
      descuento: pos.descuento || 0,
      items: pos.items.map((i) => ({ variante_id: i.variante_id, cantidad: i.cantidad, precio_unitario: i.precio_unitario })),
      pagos: pos.pagos.map((p) => ({
        metodo: p.metodo,
        monto: p.monto,
        recibido: p.metodo === 'efectivo' && p.recibido !== '' ? p.recibido : null,
        referencia: p.metodo !== 'efectivo' ? p.referencia : null
      }))
    })
    pos.limpiar()
    emit('vendido', pedido)
  } catch (error) {
    beepError()
    const { status, data } = error.response ?? {}
    if (status === 422) {
      errores.value = data.errors ?? {}
      $q.notify({ type: 'negative', message: Object.values(errores.value)[0]?.[0] ?? data.message, position: 'top', timeout: 3500 })
    } else if (status === 409) {
      $q.notify({ type: 'negative', message: Object.values(data?.errors ?? {})[0]?.[0] ?? data?.message, position: 'top', timeout: 4000 })
      await cargarCaja()
    }
  } finally {
    procesando.value = false
  }
}

function enfocarCliente () {
  clienteRef.value?.$el?.querySelector('input')?.focus()
}

defineExpose({ cobrar, enfocarCliente })
</script>

<style lang="scss" scoped>
.carrito {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  border-left: 1px solid var(--app-border-subtle);
  background: var(--app-surface);
}

.carrito__cabecera {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
  padding: 14px 16px 10px;
  border-bottom: 1px solid var(--app-border-subtle);
}

.carrito__titulo {
  font-size: 15px;
  font-weight: 700;
  color: var(--app-ink);
}

.carrito__sub {
  font-size: 12px;
  color: var(--app-ink-2);
}

.carrito__espera {
  display: flex;
  gap: 2px;
}

.carrito__esperaLista {
  color: $primary;
}

.carrito__lineas {
  flex: 1;
  min-height: 120px;
  overflow-y: auto;
  padding: 6px 8px;
}

.carrito__vacio {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 40px 12px;
  font-size: 12px;
  text-align: center;
  color: var(--app-ink-2);
}

.carrito__vacioTitulo {
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);
}

.linea {
  position: relative;
  display: flex;
  gap: 10px;
  padding: 10px 8px;
  border: 1px solid transparent;
  border-radius: 10px;
  cursor: pointer;
  transition: background 0.3s ease, border-color 0.3s ease;

  & + & {
    margin-top: 2px;
  }

  &--activa {
    border-color: rgba($primary, 0.35);
    background: rgba($primary, 0.04);
  }

  &--flash {
    background: rgba($primary, 0.16);
  }
}

.linea__thumb {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 8px;
  background: var(--app-border-subtle) center / cover;
  color: var(--app-ink-2);
}

.linea__cuerpo {
  flex: 1;
  min-width: 0;
}

.linea__nombre {
  overflow: hidden;
  padding-right: 18px;
  font-size: 13px;
  font-weight: 600;
  white-space: nowrap;
  text-overflow: ellipsis;
  color: var(--app-ink);
}

.linea__detalle {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 11.5px;
  color: var(--app-ink-2);
}

.linea__talla {
  padding: 0 5px;
  border-radius: 4px;
  background: var(--app-border-subtle);
  font-weight: 700;
  color: var(--app-ink);
}

.linea__swatch {
  width: 10px;
  height: 10px;
  border: 1px solid var(--app-border-control);
  border-radius: 50%;
}

.linea__controles {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 6px;
}

.linea__cantidad {
  display: inline-flex;
  align-items: center;
  border: 1px solid var(--app-border-control);
  border-radius: 8px;

  button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border: 0;
    background: none;
    color: var(--app-ink);
    cursor: pointer;

    &:disabled {
      color: var(--app-ink-2);
      cursor: not-allowed;
      opacity: 0.4;
    }
  }

  span {
    min-width: 24px;
    font-size: 13px;
    font-weight: 700;
    text-align: center;
  }
}

.linea__precio {
  width: 76px;
  padding: 4px 6px;
  border: 1px solid transparent;
  border-radius: 6px;
  background: transparent;
  font-size: 12px;
  color: var(--app-ink-2);

  &:hover,
  &:focus {
    border-color: var(--app-border-control);
    outline: none;
    background: var(--app-surface);
  }
}

.linea__total {
  margin-left: auto;
  font-size: 13.5px;
  font-weight: 700;
  color: var(--app-ink);
}

.linea__quitar {
  position: absolute;
  top: 8px;
  right: 6px;
  display: flex;
  padding: 2px;
  border: 0;
  border-radius: 4px;
  background: none;
  color: var(--app-ink-2);
  cursor: pointer;

  &:hover {
    color: var(--q-negative);
  }
}

.carrito__pie {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px 16px 16px;
  border-top: 1px solid var(--app-border-subtle);
}

.carrito__fila {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 13px;
  color: var(--app-ink-2);
}

.carrito__descuento {
  width: 96px;
  padding: 4px 8px;
  border: 1px solid var(--app-border-control);
  border-radius: 8px;
  background: var(--app-surface);
  font-size: 13px;
  text-align: right;
  color: var(--app-ink);
}

.carrito__total {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  padding-top: 6px;
  border-top: 1px dashed var(--app-border-control);
  font-size: 14px;
  font-weight: 600;
  color: var(--app-ink);

  .text-mono {
    font-size: 26px;
    font-weight: 800;
  }
}

.pago {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.pago__metodos {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 4px;
}

.pago__metodo {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: 6px 2px;
  border: 1px solid var(--app-border-control);
  border-radius: 8px;
  background: var(--app-surface);
  font-size: 10.5px;
  font-weight: 600;
  color: var(--app-ink-2);
  cursor: pointer;

  &--activo {
    border-color: $primary;
    background: rgba($primary, 0.08);
    color: $primary;
  }

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 1px;
  }
}

.pago__campos {
  display: grid;
  grid-template-columns: 1fr 1fr auto;
  align-items: end;
  gap: 6px;
}

.pago__campo {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 11px;
  color: var(--app-ink-2);

  input {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid var(--app-border-control);
    border-radius: 8px;
    background: var(--app-surface);
    font-size: 13px;
    color: var(--app-ink);

    &:focus {
      border-color: $primary;
      outline: none;
      box-shadow: 0 0 0 3px rgba($primary, 0.12);
    }
  }
}

.pago__quitar {
  display: flex;
  padding: 8px 4px;
  border: 0;
  background: none;
  color: var(--app-ink-2);
  cursor: pointer;
}

.pago__vuelto {
  margin: 0;
  font-size: 13px;
  color: var(--app-ink);

  strong {
    font-size: 18px;
  }

  &--falta {
    color: var(--q-negative);
  }
}

.carrito__pagosPie {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
}

.carrito__link {
  padding: 0;
  border: 0;
  background: none;
  font-weight: 600;
  color: $primary;
  cursor: pointer;
}

.carrito__restante {
  font-weight: 600;
  color: var(--q-warning);

  &--sobra {
    color: var(--q-negative);
  }
}

.carrito__caja {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border-radius: 8px;
  background: rgba($negative, 0.08);
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);

  button {
    margin-left: auto;
    padding: 4px 10px;
    border: 0;
    border-radius: 6px;
    background: $primary;
    font-weight: 600;
    color: #FFFFFF;
    cursor: pointer;
  }
}

.carrito__cobrar {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  height: 52px;
  border: 0;
  border-radius: 12px;
  background: $primary;
  font-size: 16px;
  font-weight: 700;
  color: #FFFFFF;
  cursor: pointer;

  kbd {
    padding: 1px 6px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.22);
    font-size: 11px;
    font-family: $font-mono;
  }

  &:disabled {
    background: var(--app-disabled-bg);
    color: var(--app-disabled-ink);
    cursor: not-allowed;
  }

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 2px;
  }
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}

.carrito__error {
  margin: 2px 0 0;
  font-size: 11.5px;
  color: var(--q-negative);
}
</style>
