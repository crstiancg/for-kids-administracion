<template>
  <q-select
    :model-value="null"
    :options="opciones"
    :loading="buscando"
    aria-label="Ir a otro producto"
    placeholder="Ir a otro producto…"
    use-input
    input-debounce="300"
    hide-dropdown-icon
    dense
    outlined
    class="producto-selector"
    @filter="buscar"
    @update:model-value="elegir"
  >
    <template #prepend>
      <q-icon
        name="search"
        class="producto-selector__icon"
      />
    </template>

    <template #option="scope">
      <q-item
        v-bind="scope.itemProps"
        :disable="scope.opt.id === Number(actual)"
      >
        <q-item-section avatar>
          <img
            v-if="scope.opt.portada"
            :src="scope.opt.portada.miniatura_url"
            alt=""
            class="producto-selector__avatar"
          >
          <span
            v-else
            class="producto-selector__avatar producto-selector__avatar--vacio"
          >
            <q-icon
              name="image"
              size="16px"
            />
          </span>
        </q-item-section>
        <q-item-section>
          <q-item-label class="producto-selector__nombre">
            {{ scope.opt.nombre }}
            <span
              v-if="!scope.opt.activo"
              class="producto-selector__inactivo"
            >inactivo</span>
          </q-item-label>
          <q-item-label caption>
            {{ scope.opt.categoria?.nombre }} · {{ formatearPrecio(scope.opt.precio) }}
          </q-item-label>
        </q-item-section>
        <q-item-section
          side
          :class="['text-mono', { 'producto-selector__agotado': !scope.opt.stock_total }]"
        >
          {{ scope.opt.id === Number(actual) ? 'actual' : `stock ${scope.opt.stock_total ?? 0}` }}
        </q-item-section>
      </q-item>
    </template>

    <template #no-option>
      <q-item>
        <q-item-section class="text-grey">
          {{ termino ? 'Ningún producto coincide.' : 'Escribí el nombre o un SKU.' }}
        </q-item-section>
      </q-item>
    </template>
  </q-select>
</template>

<script setup>
import { ref } from 'vue'
import ProductoService from '@/services/ProductoService'
import { formatearPrecio } from '@/utils/moneda'

/**
 * Salta de un producto a otro sin volver al listado. Busca en el servidor
 * (mismo `search` que el listado: nombre o SKU) y emite el id elegido.
 */
const props = defineProps({
  // El producto que se está viendo: aparece, pero no se puede elegir.
  actual: {
    type: [Number, String],
    default: null
  }
})

const emit = defineEmits(['elegir'])

const opciones = ref([])
const buscando = ref(false)
const termino = ref('')

// Las respuestas pueden llegar desordenadas: sólo vale la de la última búsqueda.
let ultimaBusqueda = 0

async function buscar (valor, update, abort) {
  termino.value = valor.trim()
  if (!termino.value) {
    update(() => { opciones.value = [] })
    return
  }

  const busqueda = ++ultimaBusqueda
  buscando.value = true
  try {
    const { data } = await ProductoService.getData({ params: { search: termino.value, rowsPerPage: 15, order_by: 'nombre' } })
    if (busqueda === ultimaBusqueda) update(() => { opciones.value = data })
  } catch {
    abort()
  } finally {
    if (busqueda === ultimaBusqueda) buscando.value = false
  }
}

function elegir (producto) {
  if (producto && producto.id !== Number(props.actual)) emit('elegir', producto.id)
}
</script>

<style lang="scss" scoped>
.producto-selector {
  min-width: 260px;

  :deep(.q-field__control) {
    border-radius: 10px;
    background: var(--app-surface);
  }

  :deep(.q-field__control):before {
    border-color: var(--app-border-control);
  }
}

.producto-selector__icon {
  font-size: 18px;
  color: var(--app-ink-2);
}

.producto-selector__avatar {
  display: block;
  width: 36px;
  height: 36px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 8px;
  object-fit: cover;

  &--vacio {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--app-ink-2);
  }
}

.producto-selector__nombre {
  font-weight: 600;
}

.producto-selector__inactivo {
  margin-left: 6px;
  font-size: 11px;
  font-weight: 500;
  color: var(--q-negative);
}

.producto-selector__agotado {
  color: var(--q-negative);
}
</style>
