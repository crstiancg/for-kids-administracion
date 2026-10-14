<template>
  <AppDialog
    v-model="abierto"
    :title="TIPOS[tipo].titulo"
  >
    <form
      class="rapido"
      novalidate
      @submit.prevent="guardar"
    >
      <AppTextField
        v-model="nombre"
        label="Nombre"
        :icon="TIPOS[tipo].icono"
        :maxlength="tipo === 'talla' ? 20 : 60"
        :error="errorDe('nombre')"
        autofocus
      />

      <!-- Categoría: dónde cuelga ("Niñas › Vestidos"). -->
      <div
        v-if="tipo === 'categoria'"
        class="rapido__campo"
      >
        <label class="rapido__label">Dentro de (opcional)</label>
        <q-select
          v-model="parentId"
          :options="opcionesPadre"
          emit-value
          map-options
          clearable
          dense
          outlined
          placeholder="Categoría principal"
          :error="Boolean(errorDe('parent_id'))"
          :error-message="errorDe('parent_id')"
        />
      </div>

      <!-- Talla: el orden de exhibición ("2" antes que "10"). -->
      <AppTextField
        v-if="tipo === 'talla'"
        v-model="orden"
        label="Orden (más chico = primero)"
        icon="sort"
        type="number"
        min="0"
        :error="errorDe('orden')"
      />

      <!-- Color: la muestra que se ve en las variantes y en el POS. -->
      <div
        v-if="tipo === 'color'"
        class="rapido__campo"
      >
        <label class="rapido__label">Color</label>
        <div class="rapido__color">
          <input
            v-model="hexadecimal"
            type="color"
            aria-label="Elegir color"
            class="rapido__picker"
          >
          <span class="text-mono">{{ hexadecimal.toUpperCase() }}</span>
        </div>
        <p
          v-if="errorDe('hexadecimal')"
          class="rapido__error"
        >
          {{ errorDe('hexadecimal') }}
        </p>
      </div>
    </form>

    <template #actions>
      <AppButton
        variant="tertiary"
        label="Cancelar"
        @click="abierto = false"
      />
      <AppButton
        variant="primary"
        :label="`Crear ${TIPOS[tipo].nombre}`"
        icon="add"
        :loading="guardando"
        :disable="!nombre.trim()"
        @click="guardar"
      />
    </template>
  </AppDialog>
</template>

<script>
// Fuera del setup: defineProps() se hoistea al módulo y su validator usa TIPOS.
/**
 * Crear una categoría, talla o color sin salir del form de producto: con lo
 * mínimo de cada uno. Las mismas validaciones que sus pantallas (es la
 * misma API).
 */
export const TIPOS = {
  categoria: { titulo: 'Nueva categoría', nombre: 'categoría', icono: 'category', url: 'api/categorias', clave: 'categoria' },
  talla: { titulo: 'Nueva talla', nombre: 'talla', icono: 'straighten', url: 'api/tallas', clave: 'talla' },
  color: { titulo: 'Nuevo color', nombre: 'color', icono: 'palette', url: 'api/colores', clave: 'color' }
}
</script>

<script setup>
import { computed, ref, watch } from 'vue'
import { api } from '@/boot/axios'
import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppTextField from '@/components/AppTextField.vue'
import { opcionesPadre as arbol } from '@/modules/Categorias/arbol'

const abierto = defineModel({ type: Boolean, default: false })

const props = defineProps({
  tipo: {
    type: String,
    default: 'categoria',
    validator: (v) => v in TIPOS
  },
  // Lo que el usuario ya tipeó en el select.
  nombreInicial: {
    type: String,
    default: ''
  },
  categorias: {
    type: Array,
    default: () => []
  },
  tallas: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['creado'])

const nombre = ref('')
const parentId = ref(null)
const orden = ref('')
const hexadecimal = ref('#9CA3AF')
const errores = ref({})
const guardando = ref(false)

const opcionesPadre = computed(() => arbol(props.categorias))

// Cada apertura arranca limpia, con lo tipeado y el próximo orden.
watch(abierto, (si) => {
  if (!si) return
  nombre.value = props.nombreInicial.trim()
  parentId.value = null
  orden.value = String(Math.max(0, ...props.tallas.map((t) => Number(t.orden) || 0)) + 10)
  errores.value = {}
})

function errorDe (campo) {
  return errores.value[`${TIPOS[props.tipo].clave}.${campo}`]?.[0] ?? ''
}

async function guardar () {
  if (!nombre.value.trim() || guardando.value) return
  const datos = { nombre: nombre.value.trim() }
  if (props.tipo === 'categoria') datos.parent_id = parentId.value
  if (props.tipo === 'talla') datos.orden = Number(orden.value) || 0
  if (props.tipo === 'color') datos.hexadecimal = hexadecimal.value.toUpperCase()

  guardando.value = true
  errores.value = {}
  try {
    const { data } = await api.post(TIPOS[props.tipo].url, { [TIPOS[props.tipo].clave]: datos })
    emit('creado', data)
    abierto.value = false
  } catch (error) {
    errores.value = error.response?.data?.errors ?? {}
  } finally {
    guardando.value = false
  }
}
</script>

<style lang="scss" scoped>
.rapido {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.rapido__campo {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.rapido__label {
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);
}

.rapido__color {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 13px;
  color: var(--app-ink-2);
}

.rapido__picker {
  width: 48px;
  height: 36px;
  padding: 2px;
  border: 1px solid var(--app-border-control);
  border-radius: 8px;
  background: var(--app-surface);
  cursor: pointer;
}

.rapido__error {
  margin: 0;
  font-size: 12px;
  color: var(--q-negative);
}
</style>
