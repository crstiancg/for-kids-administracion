<template>
  <q-page class="app-list-page">
    <AppPageHeader
      title="Productos"
      :subtitle="`${productos.length} productos en catálogo · ${agotadosCount} agotados`"
    >
      <template #actions>
        <AppButton
          label="Exportar CSV"
          icon="file_download"
        />
        <AppButton
          variant="primary"
          label="Nuevo producto"
          icon="add"
        />
      </template>
    </AppPageHeader>

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Filtrar por SKU o nombre"
      :has-active-filters="hasActiveFilters"
      @clear="clearFilters"
    >
      <AppFilterPill
        v-model="categoriaFilter"
        label="Categoría"
        :options="categoriaOptions"
      />
      <AppFilterPill
        v-model="estadoFilter"
        label="Estado"
        :options="estadoOptions"
      />
    </AppFilterBar>

    <AppTable
      v-model:selected="selected"
      :rows="filteredProductos"
      :columns="columns"
      row-key="id"
      selection="multiple"
      no-data-label="Ningún producto coincide con los filtros aplicados."
    >
      <template #body-cell-id="props">
        <q-td
          :props="props"
          class="text-mono"
        >
          #{{ props.row.id }}
        </q-td>
      </template>

      <template #body-cell-stock="props">
        <q-td
          :props="props"
          class="text-right text-mono"
        >
          {{ props.row.stock }}
        </q-td>
      </template>

      <template #body-cell-precio="props">
        <q-td
          :props="props"
          class="text-right text-mono"
        >
          {{ props.row.precio }}
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
            aria-label="Ver producto"
          />
          <q-btn
            flat
            dense
            round
            icon="edit"
            size="sm"
            color="grey-7"
            aria-label="Editar producto"
          />
          <q-btn
            flat
            dense
            round
            icon="delete_outline"
            size="sm"
            color="negative"
            aria-label="Eliminar producto"
          />
        </q-td>
      </template>
    </AppTable>
  </q-page>
</template>

<script setup>
import { computed, ref } from 'vue'
import AppButton from '@/components/AppButton.vue'
import AppChip from '@/components/AppChip.vue'
import AppFilterBar from '@/components/AppFilterBar.vue'
import AppFilterPill from '@/components/AppFilterPill.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppTable from '@/components/AppTable.vue'

// Mismo mapeo único que Pedidos: el label y el status del AppChip nacen
// juntos, así nunca se desincronizan.
const ESTADOS = {
  disponible: { label: 'Disponible', status: 'positive' },
  bajo: { label: 'Stock bajo', status: 'warning' },
  agotado: { label: 'Agotado', status: 'negative' }
}

const CATEGORIAS = ['Remeras', 'Pantalones', 'Camperas', 'Calzado', 'Accesorios', 'Buzos']
const NOMBRES = [
  'Remera básica', 'Campera impermeable', 'Zapatillas urbanas', 'Pantalón cargo',
  'Buzo canguro', 'Short deportivo', 'Gorra visera curva', 'Medias pack x3',
  'Mochila escolar', 'Camisa manga larga'
]
const TALLES = ['S', 'M', 'L', 'XL']

// Generador determinístico: mismos 100 productos en cada carga, sin
// depender de Math.random ni de tipear 100 filas a mano.
function buildProductos (count) {
  return Array.from({ length: count }, (_, i) => {
    const n = i + 1
    const categoria = CATEGORIAS[n % CATEGORIAS.length]
    const nombre = `${NOMBRES[n % NOMBRES.length]} ${TALLES[n % TALLES.length]}`
    const stock = (n * 7) % 60
    const estadoKey = stock === 0 ? 'agotado' : (stock < 8 ? 'bajo' : 'disponible')
    const estado = ESTADOS[estadoKey]
    const precio = 4500 + (n % 25) * 1350

    return {
      id: `SKU-${1000 + n}`,
      nombre,
      categoria,
      stock,
      estado: estado.label,
      status: estado.status,
      precio: `$${precio.toLocaleString('es-AR')}`
    }
  })
}

const productos = ref(buildProductos(100))

const columns = [
  { name: 'id', label: 'SKU', field: 'id', align: 'left', classes: 'text-mono' },
  { name: 'nombre', label: 'Producto', field: 'nombre', align: 'left' },
  { name: 'categoria', label: 'Categoría', field: 'categoria', align: 'left' },
  { name: 'stock', label: 'Stock', field: 'stock', align: 'right' },
  { name: 'precio', label: 'Precio', field: 'precio', align: 'right' },
  { name: 'estado', label: 'Estado', field: 'estado', align: 'left' },
  { name: 'acciones', label: 'Acciones', field: 'acciones', align: 'right' }
]

const selected = ref([])

const search = ref('')
const categoriaFilter = ref(null)
const estadoFilter = ref(null)

const categoriaOptions = computed(() => [
  { label: 'Todas', value: null },
  ...CATEGORIAS.map((categoria) => ({ label: categoria, value: categoria }))
])

const estadoOptions = [
  { label: 'Todos', value: null },
  ...Object.values(ESTADOS).map(({ label }) => ({ label, value: label }))
]

const hasActiveFilters = computed(() => Boolean(search.value || categoriaFilter.value || estadoFilter.value))

function clearFilters () {
  search.value = ''
  categoriaFilter.value = null
  estadoFilter.value = null
}

const filteredProductos = computed(() => {
  const term = search.value.trim().toLowerCase()

  return productos.value.filter((producto) => {
    const matchesTerm = !term
      || producto.id.toLowerCase().includes(term)
      || producto.nombre.toLowerCase().includes(term)
    const matchesCategoria = !categoriaFilter.value || producto.categoria === categoriaFilter.value
    const matchesEstado = !estadoFilter.value || producto.estado === estadoFilter.value

    return matchesTerm && matchesCategoria && matchesEstado
  })
})

const agotadosCount = computed(
  () => productos.value.filter((p) => p.estado === ESTADOS.agotado.label).length
)
</script>
