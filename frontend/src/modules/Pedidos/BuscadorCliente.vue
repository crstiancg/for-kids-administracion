<template>
  <q-select
    :model-value="model"
    :options="opciones"
    :loading="buscando"
    :aria-labelledby="ariaLabelledby"
    placeholder="Cliente varios"
    option-label="nombre"
    use-input
    fill-input
    hide-selected
    input-debounce="300"
    clearable
    dense
    outlined
    hide-bottom-space
    class="buscador-cliente"
    @filter="buscar"
    @update:model-value="model = $event"
  >
    <template #prepend>
      <q-icon
        name="person_search"
        class="buscador-cliente__icon"
      />
    </template>

    <template #option="scope">
      <q-item v-bind="scope.itemProps">
        <q-item-section>
          <q-item-label>{{ scope.opt.nombre }}</q-item-label>
          <q-item-label
            v-if="scope.opt.numero_documento || scope.opt.telefono"
            caption
          >
            <span v-if="scope.opt.numero_documento">{{ scope.opt.tipo_documento }} {{ scope.opt.numero_documento }}</span>
            <span v-if="scope.opt.numero_documento && scope.opt.telefono"> · </span>
            <span v-if="scope.opt.telefono">{{ scope.opt.telefono }}</span>
          </q-item-label>
        </q-item-section>
      </q-item>
    </template>

    <template #no-option>
      <q-item>
        <q-item-section class="text-grey">
          {{ termino ? 'Ningún cliente coincide.' : 'Escribí nombre, documento o teléfono.' }}
        </q-item-section>
      </q-item>
    </template>
  </q-select>
</template>

<script setup>
import { ref } from 'vue'
import ClienteService from '@/services/ClienteService'

/**
 * Elige un cliente buscando en el servidor. El modelo es el cliente completo
 * (para mostrarlo sin otra consulta); vacío = "Cliente varios".
 */
defineProps({
  ariaLabelledby: {
    type: String,
    default: undefined
  }
})

const model = defineModel({ type: Object, default: null })

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
    const { data } = await ClienteService.getData({ params: { search: termino.value, rowsPerPage: 10, order_by: 'nombre' } })
    if (busqueda === ultimaBusqueda) update(() => { opciones.value = data })
  } catch {
    abort()
  } finally {
    if (busqueda === ultimaBusqueda) buscando.value = false
  }
}
</script>

<style lang="scss" scoped>
.buscador-cliente {
  :deep(.q-field__control) {
    border-radius: 10px;
    background: var(--app-surface);
  }

  :deep(.q-field__control):before {
    border-color: var(--app-border-control);
  }

  &.q-field--focused :deep(.q-field__control) {
    box-shadow: 0 0 0 3px rgba($primary, 0.12);
  }
}

.buscador-cliente__icon {
  color: var(--app-ink-2);
}
</style>
