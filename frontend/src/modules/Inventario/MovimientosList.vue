<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Inventario"
      :subtitle="`${pagination.rowsNumber} movimientos registrados`"
    >
      <template #actions>
        <AppButton
          v-for="accion in acciones"
          :key="accion.tipo"
          :variant="accion.tipo === 'entrada' ? 'primary' : 'secondary'"
          :label="accion.titulo"
          :icon="accion.icon"
          @click="abrir(accion.tipo)"
        />
      </template>
    </AppPageHeader>

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por SKU, producto o referencia"
      :has-active-filters="hayFiltros"
      @clear="limpiarFiltros"
    >
      <AppFilterPill
        v-model="tipoFilter"
        label="Tipo"
        :options="tipoOptions"
      />
    </AppFilterBar>

    <AppTable
      ref="tableRef"
      v-model:pagination="pagination"
      :rows="rows"
      :columns="columns"
      :loading="loading"
      :filter="filtroTabla"
      no-data-label="Todavía no hay movimientos que coincidan."
      @request="onRequest"
    >
      <template #body-cell-fecha="props">
        <q-td
          :props="props"
          class="text-mono movimiento-fecha"
        >
          {{ formatearFecha(props.row.fecha) }}
        </q-td>
      </template>

      <template #body-cell-tipo="props">
        <q-td :props="props">
          <AppChip
            :status="TIPOS[props.row.tipo].status"
            :label="TIPOS[props.row.tipo].label"
          />
        </q-td>
      </template>

      <template #body-cell-variante="props">
        <q-td :props="props">
          <div class="movimiento-variante">
            <span
              class="movimiento-swatch"
              :style="{ background: props.row.variante.color?.hexadecimal }"
            />
            <div>
              <div class="movimiento-producto">
                {{ props.row.variante.producto?.nombre }}
              </div>
              <div class="movimiento-detalle">
                Talla {{ props.row.variante.talla }} · {{ props.row.variante.color?.nombre }} ·
                <span class="text-mono">{{ props.row.variante.sku }}</span>
              </div>
            </div>
          </div>
        </q-td>
      </template>

      <template #body-cell-cantidad="props">
        <q-td
          :props="props"
          :class="['text-right', 'text-mono', props.row.cantidad > 0 ? 'movimiento-mas' : 'movimiento-menos']"
        >
          {{ props.row.cantidad > 0 ? `+${props.row.cantidad}` : props.row.cantidad }}
        </q-td>
      </template>

      <template #body-cell-detalle="props">
        <q-td :props="props">
          <div v-if="props.row.motivo_label">
            {{ props.row.motivo_label }}
          </div>
          <div
            v-if="props.row.referencia"
            class="movimiento-detalle"
          >
            Ref. {{ props.row.referencia }}
          </div>
          <div
            v-if="props.row.costo_unitario !== null"
            class="movimiento-detalle"
          >
            {{ formatearPrecio(props.row.costo_unitario) }} c/u
          </div>
          <div
            v-if="props.row.observacion"
            class="movimiento-detalle movimiento-observacion"
            :title="props.row.observacion"
          >
            {{ props.row.observacion }}
          </div>
        </q-td>
      </template>
    </AppTable>

    <AppDialog
      v-model="formDialog"
      :title="tipoAbierto ? TIPOS[tipoAbierto].titulo : ''"
      size="lg"
      persistent
    >
      <MovimientoForm
        v-if="tipoAbierto"
        ref="formRef"
        :key="`${tipoAbierto}-${aperturas}`"
        :tipo="tipoAbierto"
        @save="save"
      />

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="formDialog = false"
        />
        <AppButton
          variant="primary"
          label="Registrar"
          :loading="formRef?.form.processing"
          @click="formRef.submit()"
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
import InventarioService from '@/services/InventarioService'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'
import MovimientoForm from './MovimientoForm.vue'
import { TIPOS } from './constantes'

const $q = useQuasar()
const userStore = useUserStore()

// Cada botón aparece sólo con su permiso.
const acciones = computed(() => Object.entries(TIPOS)
  .filter(([, tipo]) => userStore.hasPermission(tipo.permiso))
  .map(([clave, tipo]) => ({ tipo: clave, ...tipo })))

const columns = [
  { name: 'fecha', label: 'Fecha', field: 'fecha', align: 'left', sortable: true },
  { name: 'tipo', label: 'Tipo', field: 'tipo', align: 'left' },
  { name: 'variante', label: 'Variante', field: (row) => row.variante?.sku, align: 'left' },
  { name: 'cantidad', label: 'Cantidad', field: 'cantidad', align: 'right' },
  { name: 'stock_resultante', label: 'Stock', field: 'stock_resultante', align: 'right', classes: 'text-mono' },
  { name: 'detalle', label: 'Detalle', field: 'motivo', align: 'left' },
  { name: 'usuario', label: 'Usuario', field: (row) => row.usuario?.name ?? '—', align: 'left' }
]

const formatoFecha = new Intl.DateTimeFormat('es-PE', { dateStyle: 'short', timeStyle: 'short' })
function formatearFecha (iso) {
  return iso ? formatoFecha.format(new Date(iso)) : ''
}

// ── Filtros ──
const search = ref('')
const busqueda = ref('')
const tipoFilter = ref(null)

let searchTimer
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { busqueda.value = value.trim() }, 400)
})

const tipoOptions = [
  { label: 'Todos', value: null },
  ...Object.entries(TIPOS).map(([value, { label }]) => ({ label, value }))
]

const hayFiltros = computed(() => Boolean(search.value || tipoFilter.value !== null))

function limpiarFiltros () {
  search.value = ''
  tipoFilter.value = null
}

// AppTable vuelve a la página 1 y pide datos cuando cambia `filter`.
const filtroTabla = computed(() => JSON.stringify({ search: busqueda.value, tipo: tipoFilter.value }))

// ── Tabla (paginación en el servidor) ──
const tableRef = ref()
const rows = ref([])
const loading = ref(false)
// El más nuevo primero: "fecha" ordena por id (mismo orden, sin empates).
const pagination = ref({ sortBy: 'fecha', descending: true, page: 1, rowsPerPage: 20, rowsNumber: 0 })

async function onRequest ({ pagination: requested }) {
  const { page, rowsPerPage, sortBy, descending } = requested
  loading.value = true

  try {
    const columna = sortBy === 'fecha' ? 'id' : sortBy
    const params = { rowsPerPage, page, search: busqueda.value, order_by: descending ? `-${columna}` : columna }
    if (tipoFilter.value) params.tipo = tipoFilter.value

    const { data, total = 0 } = await InventarioService.getData({ params })

    rows.value = data
    pagination.value = { ...requested, rowsNumber: total }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  tableRef.value.requestServerInteraction()
})

// ── Registrar ──
const formDialog = ref(false)
const formRef = ref()
const tipoAbierto = ref(null)
// Cada apertura monta un form nuevo (sin líneas de la vez anterior).
const aperturas = ref(0)

function abrir (tipo) {
  tipoAbierto.value = tipo
  aperturas.value++
  formDialog.value = true
}

function save (movimientos) {
  formDialog.value = false
  tableRef.value.requestServerInteraction()

  const n = movimientos.length
  $q.notify({
    type: 'positive',
    message: n === 0
      ? 'El conteo coincide con el sistema: no hubo diferencias que registrar.'
      : `${n} ${n === 1 ? 'movimiento registrado' : 'movimientos registrados'}.`,
    position: 'top-right',
    timeout: 2500
  })
}
</script>

<style lang="scss" scoped>
.movimiento-fecha {
  white-space: nowrap;
  color: var(--app-ink-2);
}

.movimiento-variante {
  display: flex;
  align-items: flex-start;
  gap: 8px;
}

.movimiento-swatch {
  flex-shrink: 0;
  width: 14px;
  height: 14px;
  margin-top: 3px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 4px;
}

.movimiento-producto {
  font-weight: 600;
  color: var(--app-ink);
}

.movimiento-detalle {
  font-size: 12px;
  color: var(--app-ink-2);
}

.movimiento-observacion {
  max-width: 220px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.movimiento-mas {
  font-weight: 600;
  color: var(--q-positive);
}

.movimiento-menos {
  font-weight: 600;
  color: var(--q-negative);
}
</style>
