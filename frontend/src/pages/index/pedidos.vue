<template>
  <q-page class="app-list-page">
    <AppPageHeader
      title="Pedidos"
      :subtitle="`${pedidos.length} pedidos en total · ${pendientesCount} requieren atención`"
    >
      <template #actions>
        <AppButton
          label="Exportar CSV"
          icon="file_download"
        />
        <AppButton
          variant="primary"
          label="Nuevo pedido"
          icon="add"
          @click="openNuevoPedido"
        />
      </template>
    </AppPageHeader>

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Filtrar por N° o cliente"
      :has-active-filters="hasActiveFilters"
      @clear="clearFilters"
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
      v-model:selected="selected"
      :rows="filteredPedidos"
      :columns="columns"
      row-key="id"
      selection="multiple"
      no-data-label="Ningún pedido coincide con los filtros aplicados."
    >
      <template #body-cell-id="props">
        <q-td
          :props="props"
          class="text-mono"
        >
          #{{ props.row.id }}
        </q-td>
      </template>

      <template #body-cell-estado="props">
        <q-td :props="props">
          <AppChip
            :status="props.row.status"
            :label="props.row.estado"
          />
        </q-td>
      </template>

      <template #body-cell-total="props">
        <q-td
          :props="props"
          class="text-right text-mono"
        >
          {{ props.row.total }}
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
            aria-label="Ver pedido"
          />
          <q-btn
            flat
            dense
            round
            icon="edit"
            size="sm"
            color="grey-7"
            aria-label="Editar pedido"
          />
          <q-btn
            flat
            dense
            round
            icon="delete_outline"
            size="sm"
            color="negative"
            aria-label="Eliminar pedido"
          />
        </q-td>
      </template>
    </AppTable>

    <AppDialog
      v-model="showNuevoPedido"
      title="Nuevo pedido"
    >
      <PedidosForm v-model="draftPedido" />

      <template #actions>
        <AppButton
          label="Cancelar"
          @click="showNuevoPedido = false"
        />
        <AppButton
          variant="primary"
          label="Guardar pedido"
          :disable="!isDraftValid"
          @click="guardarNuevoPedido"
        />
      </template>
    </AppDialog>
  </q-page>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import AppButton from '@/components/AppButton.vue'
import AppChip from '@/components/AppChip.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppFilterBar from '@/components/AppFilterBar.vue'
import AppFilterPill from '@/components/AppFilterPill.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppTable from '@/components/AppTable.vue'
import PedidosForm from '@/components/PedidosForm.vue'

// Mapeo único: el label en español y el status del AppChip nunca pueden
// desincronizarse porque nacen del mismo lugar.
const ESTADOS = {
  completado: { label: 'Completado', status: 'positive' },
  pendiente: { label: 'Pendiente', status: 'warning' },
  proceso: { label: 'En proceso', status: 'info' },
  cancelado: { label: 'Cancelado', status: 'negative' }
}

const pedidos = ref([
  { id: 'PD-4821', cliente: 'Marina Alvarez', fecha: '12 dic 2025', canal: 'Búsqueda orgánica', estado: ESTADOS.completado.label, status: ESTADOS.completado.status, total: '$48.900' },
  { id: 'PD-4820', cliente: 'Diego Ferreyra', fecha: '12 dic 2025', canal: 'Campañas pagas', estado: ESTADOS.pendiente.label, status: ESTADOS.pendiente.status, total: '$12.400' },
  { id: 'PD-4819', cliente: 'Sofía Beltrán', fecha: '11 dic 2025', canal: 'Referidos', estado: ESTADOS.cancelado.label, status: ESTADOS.cancelado.status, total: '$7.150' },
  { id: 'PD-4818', cliente: 'Tomás Quiroga', fecha: '11 dic 2025', canal: 'Tráfico directo', estado: ESTADOS.proceso.label, status: ESTADOS.proceso.status, total: '$96.300' },
  { id: 'PD-4817', cliente: 'Lucía Miranda', fecha: '10 dic 2025', canal: 'Búsqueda orgánica', estado: ESTADOS.completado.label, status: ESTADOS.completado.status, total: '$23.780' },
  { id: 'PD-4816', cliente: 'Ignacio Peralta', fecha: '10 dic 2025', canal: 'Campañas pagas', estado: ESTADOS.pendiente.label, status: ESTADOS.pendiente.status, total: '$5.020' },
  { id: 'PD-4815', cliente: 'Valentina Rossi', fecha: '09 dic 2025', canal: 'Referidos', estado: ESTADOS.completado.label, status: ESTADOS.completado.status, total: '$134.500' },
  { id: 'PD-4814', cliente: 'Federico Ansaldi', fecha: '09 dic 2025', canal: 'Tráfico directo', estado: ESTADOS.proceso.label, status: ESTADOS.proceso.status, total: '$31.660' }
])

const columns = [
  { name: 'id', label: 'Pedido', field: 'id', align: 'left', classes: 'text-mono' },
  { name: 'cliente', label: 'Cliente', field: 'cliente', align: 'left' },
  { name: 'fecha', label: 'Fecha', field: 'fecha', align: 'left' },
  { name: 'canal', label: 'Canal', field: 'canal', align: 'left' },
  { name: 'estado', label: 'Estado', field: 'estado', align: 'left' },
  { name: 'total', label: 'Total', field: 'total', align: 'right' },
  { name: 'acciones', label: 'Acciones', field: 'acciones', align: 'right' }
]

const selected = ref([])

const search = ref('')
const estadoFilter = ref(null)
const canalFilter = ref(null)

const estadoOptions = [
  { label: 'Todos', value: null },
  ...Object.values(ESTADOS).map(({ label }) => ({ label, value: label }))
]

const canalOptions = computed(() => [
  { label: 'Todos', value: null },
  ...[...new Set(pedidos.value.map((p) => p.canal))].map((canal) => ({ label: canal, value: canal }))
])

const hasActiveFilters = computed(() => Boolean(search.value || estadoFilter.value || canalFilter.value))

function clearFilters () {
  search.value = ''
  estadoFilter.value = null
  canalFilter.value = null
}

const filteredPedidos = computed(() => {
  const term = search.value.trim().toLowerCase()

  return pedidos.value.filter((pedido) => {
    const matchesTerm = !term
      || pedido.id.toLowerCase().includes(term)
      || pedido.cliente.toLowerCase().includes(term)
    const matchesEstado = !estadoFilter.value || pedido.estado === estadoFilter.value
    const matchesCanal = !canalFilter.value || pedido.canal === canalFilter.value

    return matchesTerm && matchesEstado && matchesCanal
  })
})

const pendientesCount = computed(
  () => pedidos.value.filter((p) => p.estado === ESTADOS.pendiente.label).length
)

// ══ Alta de pedido ══
const showNuevoPedido = ref(false)

function draftPedidoVacio () {
  return { cliente: '', canal: null, estado: null, total: '' }
}

const draftPedido = ref(draftPedidoVacio())

// Cubre las tres formas de cerrar el diálogo (Cancelar, la X, click afuera):
// todas bajan showNuevoPedido, así que reseteando acá no hay que repetirlo
// en cada handler.
watch(showNuevoPedido, (open) => {
  if (!open) {
    draftPedido.value = draftPedidoVacio()
  }
})

const isDraftValid = computed(() => Boolean(
  draftPedido.value.cliente.trim() && draftPedido.value.canal && draftPedido.value.estado && draftPedido.value.total
))

function openNuevoPedido () {
  showNuevoPedido.value = true
}

// "12 dic 2025", igual que los datos de ejemplo. toLocaleDateString con
// es-AR depende del ICU del navegador (algunos dan "12 de dic. de 2025") y
// rompía la consistencia visual de la columna Fecha.
const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic']

function formatFecha (date) {
  const dia = String(date.getDate()).padStart(2, '0')
  return `${dia} ${MESES[date.getMonth()]} ${date.getFullYear()}`
}

function guardarNuevoPedido () {
  const estadoInfo = Object.values(ESTADOS).find((e) => e.label === draftPedido.value.estado)
  const siguienteNumero = Math.max(...pedidos.value.map((p) => Number(p.id.split('-')[1]))) + 1
  const hoy = formatFecha(new Date())

  pedidos.value.unshift({
    id: `PD-${siguienteNumero}`,
    cliente: draftPedido.value.cliente.trim(),
    fecha: hoy,
    canal: draftPedido.value.canal,
    estado: estadoInfo.label,
    status: estadoInfo.status,
    total: `$${Number(draftPedido.value.total).toLocaleString('es-AR')}`
  })

  showNuevoPedido.value = false
}
</script>
