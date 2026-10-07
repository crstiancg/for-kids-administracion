<template>
  <!-- El libro de inventario de un solo producto: misma API que Inventario
       (`producto_id`), sin filtros ni registrar; eso vive en su pantalla. -->
  <AppTable
    ref="tableRef"
    v-model:pagination="pagination"
    :rows="rows"
    :columns="columns"
    :loading="loading"
    no-data-label="Este producto todavía no tiene movimientos."
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
            <div>Talla {{ props.row.variante.talla }} · {{ props.row.variante.color?.nombre }}</div>
            <div class="movimiento-detalle text-mono">
              {{ props.row.variante.sku }}
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
</template>

<script setup>
import { onMounted, ref } from 'vue'
import AppChip from '@/components/AppChip.vue'
import AppTable from '@/components/AppTable.vue'
import InventarioService from '@/services/InventarioService'
import { formatearPrecio } from '@/utils/moneda'
import { TIPOS } from './constantes'

const props = defineProps({
  productoId: {
    type: [Number, String],
    required: true
  }
})

const columns = [
  { name: 'fecha', label: 'Fecha', field: 'fecha', align: 'left' },
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

const tableRef = ref()
const rows = ref([])
const loading = ref(false)
const pagination = ref({ page: 1, rowsPerPage: 10, rowsNumber: 0 })

async function onRequest ({ pagination: requested }) {
  const { page, rowsPerPage } = requested
  loading.value = true

  try {
    const { data, total = 0 } = await InventarioService.getData({
      // El más nuevo primero.
      params: { producto_id: props.productoId, page, rowsPerPage, order_by: '-id' }
    })

    rows.value = data
    pagination.value = { ...requested, rowsNumber: total }
  } finally {
    loading.value = false
  }
}

function recargar () {
  tableRef.value.requestServerInteraction()
}

onMounted(recargar)

defineExpose({ recargar })
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
