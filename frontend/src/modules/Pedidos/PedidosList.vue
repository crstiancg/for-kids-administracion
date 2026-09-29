<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Pedidos"
      :subtitle="`${pagination.rowsNumber} pedidos`"
    >
      <template #actions>
        <AppButton
          v-if="userStore.hasPermission('pedidos.store')"
          variant="primary"
          label="Nuevo pedido"
          icon="add_shopping_cart"
          @click="crear"
        />
      </template>
    </AppPageHeader>

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por código, cliente o documento"
      :has-active-filters="hayFiltros"
      @clear="limpiarFiltros"
    >
      <AppFilterPill
        v-model="estadoFilter"
        label="Estado"
        :options="estadoOptions"
      />
      <AppFilterPill
        v-model="canalFilter"
        label="Canal"
        :options="canalOptions"
      />
    </AppFilterBar>

    <AppTable
      ref="tableRef"
      v-model:pagination="pagination"
      :rows="rows"
      :columns="columns"
      :loading="loading"
      :filter="filtroTabla"
      no-data-label="No hay pedidos que coincidan."
      @request="onRequest"
    >
      <template #body-cell-codigo="props">
        <q-td :props="props">
          <button
            type="button"
            class="pedido-codigo text-mono"
            @click="ver(props.row)"
          >
            {{ props.row.codigo }}
          </button>
          <div class="pedido-detalle">
            {{ formatearFecha(props.row.fecha) }}
          </div>
        </q-td>
      </template>

      <template #body-cell-cliente="props">
        <q-td :props="props">
          <span v-if="props.row.cliente">{{ props.row.cliente.nombre }}</span>
          <span
            v-else
            class="pedido-detalle"
          >Cliente varios</span>
        </q-td>
      </template>

      <template #body-cell-total="props">
        <q-td
          :props="props"
          class="text-right text-mono"
        >
          {{ formatearPrecio(props.row.total) }}
        </q-td>
      </template>

      <template #body-cell-estado="props">
        <q-td :props="props">
          <AppChip
            :status="ESTADOS[props.row.estado].status"
            :label="ESTADOS[props.row.estado].label"
          />
        </q-td>
      </template>

      <template #body-cell-acciones="props">
        <q-td
          :props="props"
          class="text-right"
        >
          <q-btn
            flat
            dense
            round
            icon="visibility"
            size="sm"
            color="grey-7"
            :aria-label="`Ver ${props.row.codigo}`"
            @click="ver(props.row)"
          />
          <q-btn
            v-if="props.row.editable && userStore.hasPermission('pedidos.update')"
            flat
            dense
            round
            icon="edit"
            size="sm"
            color="grey-7"
            :aria-label="`Editar ${props.row.codigo}`"
            @click="editar(props.row)"
          />
        </q-td>
      </template>
    </AppTable>

    <!-- ── Crear / editar ── -->
    <AppDialog
      v-model="formDialog"
      :title="editId ? 'Editar pedido' : 'Nuevo pedido'"
      size="lg"
      persistent
    >
      <PedidoForm
        :id="editId"
        ref="formRef"
        :key="editId ?? `nuevo-${aperturas}`"
        @save="guardado"
      />

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="formDialog = false"
        />
        <AppButton
          variant="primary"
          label="Guardar"
          :loading="formRef?.form.processing"
          @click="formRef.submit()"
        />
      </template>
    </AppDialog>

    <!-- ── Detalle con acciones según estado y permiso ── -->
    <AppDialog
      v-model="detalleDialog"
      title="Pedido"
    >
      <PedidoDetalle
        v-if="detalleId"
        ref="detalleRef"
        :key="detalleId"
        :id="detalleId"
      />

      <template #actions>
        <AppButton
          v-if="puede('cancelar')"
          variant="tertiary"
          label="Cancelar pedido"
          @click="pedirConfirmacion('cancelar')"
        />
        <AppButton
          v-if="puede('editar')"
          variant="secondary"
          label="Editar"
          icon="edit"
          @click="editar(detalle)"
        />
        <AppButton
          v-if="puede('confirmar')"
          variant="primary"
          label="Confirmar"
          icon="check"
          @click="pedirConfirmacion('confirmar')"
        />
        <AppButton
          v-if="puede('entregar')"
          variant="primary"
          label="Marcar entregado"
          icon="local_shipping"
          :loading="accionando === 'entregar'"
          @click="ejecutar('entregar')"
        />
      </template>
    </AppDialog>

    <!-- ── Confirmar / cancelar: mueven stock, se pregunta antes ── -->
    <AppDialog
      v-model="accionDialog"
      :title="accionPendiente === 'confirmar' ? 'Confirmar pedido' : 'Cancelar pedido'"
    >
      <p class="confirm-text">
        <template v-if="accionPendiente === 'confirmar'">
          Al confirmar <strong>{{ detalle?.codigo }}</strong> se descuenta el stock de sus productos y ya no se puede editar.
        </template>
        <template v-else-if="detalle?.estado === 'confirmado'">
          ¿Cancelar <strong>{{ detalle?.codigo }}</strong>? Sus productos vuelven al stock.
        </template>
        <template v-else>
          ¿Cancelar <strong>{{ detalle?.codigo }}</strong>? Todavía no había descontado stock.
        </template>
      </p>

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Volver"
          @click="accionDialog = false"
        />
        <AppButton
          :variant="accionPendiente === 'cancelar' ? 'destructive' : 'primary'"
          :label="accionPendiente === 'confirmar' ? 'Confirmar' : 'Cancelar pedido'"
          :loading="accionando === accionPendiente"
          @click="ejecutar(accionPendiente)"
        />
      </template>
    </AppDialog>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import AppButton from '@/components/AppButton.vue'
import AppChip from '@/components/AppChip.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppFilterBar from '@/components/AppFilterBar.vue'
import AppFilterPill from '@/components/AppFilterPill.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppTable from '@/components/AppTable.vue'
import PedidoService from '@/services/PedidoService'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'
import PedidoDetalle from './PedidoDetalle.vue'
import PedidoForm from './PedidoForm.vue'
import { CANALES, ESTADOS } from './constantes'

const $q = useQuasar()
const userStore = useUserStore()

const columns = [
  { name: 'codigo', label: 'Pedido', field: 'codigo', align: 'left', sortable: true },
  { name: 'cliente', label: 'Cliente', field: (row) => row.cliente?.nombre, align: 'left' },
  { name: 'canal', label: 'Canal', field: 'canal_label', align: 'left' },
  { name: 'items_count', label: 'Ítems', field: 'items_count', align: 'right', classes: 'text-mono' },
  { name: 'total', label: 'Total', field: 'total', align: 'right', sortable: true },
  { name: 'estado', label: 'Estado', field: 'estado', align: 'left' },
  { name: 'acciones', label: '', field: 'id', align: 'right' }
]

const formatoFecha = new Intl.DateTimeFormat('es-PE', { dateStyle: 'short', timeStyle: 'short' })
function formatearFecha (iso) {
  return iso ? formatoFecha.format(new Date(iso)) : ''
}

// ── Filtros ──
const search = ref('')
const busqueda = ref('')
const estadoFilter = ref(null)
const canalFilter = ref(null)

let searchTimer
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { busqueda.value = value.trim() }, 400)
})

const estadoOptions = [
  { label: 'Todos', value: null },
  ...Object.entries(ESTADOS).map(([value, { label }]) => ({ label, value }))
]
const canalOptions = [{ label: 'Todos', value: null }, ...CANALES]

const hayFiltros = computed(() => Boolean(search.value || estadoFilter.value || canalFilter.value))

function limpiarFiltros () {
  search.value = ''
  estadoFilter.value = null
  canalFilter.value = null
}

// AppTable vuelve a la página 1 y pide datos cuando cambia `filter`.
const filtroTabla = computed(() => JSON.stringify({ search: busqueda.value, estado: estadoFilter.value, canal: canalFilter.value }))

// ── Tabla (paginación en el servidor) ──
const tableRef = ref()
const rows = ref([])
const loading = ref(false)
// El más nuevo primero: "codigo" ordena por id (mismo orden, sin empates).
const pagination = ref({ sortBy: 'codigo', descending: true, page: 1, rowsPerPage: 20, rowsNumber: 0 })

async function onRequest ({ pagination: requested }) {
  const { page, rowsPerPage, sortBy, descending } = requested
  loading.value = true

  try {
    const columna = sortBy === 'codigo' ? 'id' : sortBy
    const params = { rowsPerPage, page, search: busqueda.value, order_by: descending ? `-${columna}` : columna }
    if (estadoFilter.value) params.estado = estadoFilter.value
    if (canalFilter.value) params.canal = canalFilter.value

    const { data, total = 0 } = await PedidoService.getData({ params })

    rows.value = data
    pagination.value = { ...requested, rowsNumber: total }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  tableRef.value.requestServerInteraction()
})

function refrescar () {
  tableRef.value.requestServerInteraction()
}

// ── Crear / editar ──
const formDialog = ref(false)
const formRef = ref()
const editId = ref(null)
const aperturas = ref(0)

function crear () {
  editId.value = null
  aperturas.value++
  formDialog.value = true
}

function editar (pedido) {
  detalleDialog.value = false
  editId.value = pedido.id
  formDialog.value = true
}

// Recién guardado: se abre su detalle, que es donde se confirma.
function guardado (pedido) {
  formDialog.value = false
  refrescar()
  $q.notify({ type: 'positive', message: `Pedido ${pedido?.codigo ?? ''} guardado.`, position: 'top-right', timeout: 1500 })
  if (pedido) ver(pedido)
}

// ── Detalle y acciones ──
const detalleDialog = ref(false)
const detalleRef = ref()
const detalleId = ref(null)
const detalle = computed(() => detalleRef.value?.pedido ?? null)

function ver (pedido) {
  detalleId.value = pedido.id
  detalleDialog.value = true
}

const REGLAS = {
  editar: { estados: ['pendiente'], permiso: 'pedidos.update' },
  confirmar: { estados: ['pendiente'], permiso: 'pedidos.confirmar' },
  entregar: { estados: ['confirmado'], permiso: 'pedidos.entregar' },
  cancelar: { estados: ['pendiente', 'confirmado'], permiso: 'pedidos.cancelar' }
}

function puede (accion) {
  const regla = REGLAS[accion]
  return Boolean(detalle.value) && regla.estados.includes(detalle.value.estado) && userStore.hasPermission(regla.permiso)
}

const accionDialog = ref(false)
const accionPendiente = ref(null)
const accionando = ref(null)

function pedirConfirmacion (accion) {
  accionPendiente.value = accion
  accionDialog.value = true
}

const MENSAJES = {
  confirmar: 'Pedido confirmado: se descontó el stock.',
  entregar: 'Pedido entregado.',
  cancelar: 'Pedido cancelado.'
}

async function ejecutar (accion) {
  accionando.value = accion
  try {
    const actualizado = await PedidoService[accion](detalle.value.id)
    detalleRef.value.actualizar(actualizado)
    accionDialog.value = false
    refrescar()
    $q.notify({ type: 'positive', message: MENSAJES[accion], position: 'top-right', timeout: 2000 })
  } catch (error) {
    accionDialog.value = false
    // 422: falta stock en algún ítem; 409: el estado cambió mientras tanto.
    const { status, data } = error.response ?? {}
    if (status === 422 || status === 409) {
      const primero = Object.values(data?.errors ?? {})[0]?.[0]
      $q.notify({ type: 'negative', message: primero ?? data?.message, position: 'top-right', timeout: 4000 })
      if (status === 409) detalleRef.value.actualizar(await PedidoService.get(detalle.value.id))
    }
  } finally {
    accionando.value = null
  }
}
</script>

<style lang="scss" scoped>
.pedido-codigo {
  padding: 0;
  border: 0;
  background: none;
  font-weight: 600;
  color: $primary;
  cursor: pointer;

  &:hover {
    text-decoration: underline;
  }

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 2px;
  }
}

.pedido-detalle {
  font-size: 12px;
  color: var(--app-ink-2);
}

.confirm-text {
  margin: 0;
  font-size: 14px;
  line-height: 1.55;
  color: var(--app-ink-2);

  strong {
    color: var(--app-ink);
  }
}
</style>
