<template>
  <q-select
    ref="selectRef"
    :model-value="null"
    :options="opciones"
    :loading="buscando"
    use-input
    hide-dropdown-icon
    input-debounce="250"
    borderless
    dense
    placeholder="Buscar productos, pedidos, clientes…"
    aria-label="Buscar productos, pedidos y clientes"
    class="buscador"
    popup-content-class="buscador__popup"
    @filter="buscar"
    @update:model-value="ir"
  >
    <template #prepend>
      <q-icon
        name="search"
        class="buscador__icon"
      />
    </template>

    <template #append>
      <kbd
        v-if="!buscando"
        class="buscador__atajo gt-sm"
      >Ctrl K</kbd>
    </template>

    <template #option="scope">
      <!-- Encabezado de grupo antes del primer resultado de cada tipo. -->
      <q-item-label
        v-if="scope.index === 0 || opciones[scope.index - 1].tipo !== scope.opt.tipo"
        header
        class="buscador__grupo"
      >
        {{ TIPOS[scope.opt.tipo].grupo }}
      </q-item-label>
      <q-item v-bind="scope.itemProps">
        <q-item-section avatar>
          <img
            v-if="scope.opt.imagen"
            :src="scope.opt.imagen"
            alt=""
            class="buscador__img"
          >
          <span
            v-else
            class="buscador__img buscador__img--icono"
          >
            <q-icon
              :name="TIPOS[scope.opt.tipo].icono"
              size="18px"
            />
          </span>
        </q-item-section>
        <q-item-section>
          <q-item-label class="buscador__titulo">
            {{ scope.opt.titulo }}
          </q-item-label>
          <q-item-label caption>
            {{ scope.opt.detalle }}
          </q-item-label>
        </q-item-section>
      </q-item>
    </template>

    <template #no-option>
      <q-item>
        <q-item-section class="buscador__vacio">
          {{ termino.length < 2 ? 'Escribí al menos 2 letras: nombre, SKU, código de pedido, DNI…' : 'Nada coincide con tu búsqueda.' }}
        </q-item-section>
      </q-item>
    </template>
  </q-select>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import BuscarService from '@/services/BuscarService'

/**
 * El buscador del header: productos (nombre, SKU o código de barras),
 * pedidos (P-000123 o 123) y clientes (nombre, documento, teléfono). Elegir
 * un resultado lleva directo a él. Ctrl+K lo enfoca desde cualquier pantalla.
 */
const TIPOS = {
  producto: { grupo: 'Productos', icono: 'inventory_2' },
  pedido: { grupo: 'Pedidos', icono: 'receipt_long' },
  cliente: { grupo: 'Clientes', icono: 'person' }
}

const router = useRouter()
const selectRef = ref()
const opciones = ref([])
const buscando = ref(false)
const termino = ref('')

// Las respuestas pueden llegar desordenadas: sólo vale la última búsqueda.
let ultima = 0

async function buscar (valor, update, abort) {
  termino.value = valor.trim()
  if (termino.value.length < 2) {
    update(() => { opciones.value = [] })
    return
  }

  const busqueda = ++ultima
  buscando.value = true
  try {
    const resultados = await BuscarService.buscar(termino.value)
    if (busqueda === ultima) update(() => { opciones.value = resultados })
  } catch {
    abort()
  } finally {
    if (busqueda === ultima) buscando.value = false
  }
}

function ir (resultado) {
  if (!resultado) return
  selectRef.value?.updateInputValue('', true)
  selectRef.value?.blur()
  router.push(resultado.ruta)
}

function atajo (evento) {
  if ((evento.ctrlKey || evento.metaKey) && evento.key.toLowerCase() === 'k') {
    evento.preventDefault()
    selectRef.value?.focus()
  }
}

onMounted(() => window.addEventListener('keydown', atajo))
onBeforeUnmount(() => window.removeEventListener('keydown', atajo))
</script>

<style lang="scss" scoped>
.buscador {
  width: 380px;
  max-width: 100%;
  padding: 0 12px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 9px;
  background: var(--app-page);
  font-size: 13.5px;

  &.q-field--focused {
    border-color: var(--app-border-control-hover);
  }
}

.buscador__icon {
  font-size: 17px;
  color: var(--app-ink-2);
}

.buscador__atajo {
  padding: 2px 6px;
  border: 1px solid var(--app-border-control);
  border-radius: 5px;
  font-family: inherit;
  font-size: 11px;
  color: var(--app-ink-2);
}

.buscador__grupo {
  padding: 10px 16px 4px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.6px;
  text-transform: uppercase;
}

.buscador__img {
  display: block;
  width: 34px;
  height: 34px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 8px;
  object-fit: cover;

  &--icono {
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--app-page);
    color: var(--app-ink-2);
  }
}

.buscador__titulo {
  font-weight: 600;
}

.buscador__vacio {
  font-size: 13px;
  color: var(--app-ink-2);
}
</style>
