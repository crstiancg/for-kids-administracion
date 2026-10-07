<template>
  <!-- Barras horizontales de UNA serie: un solo color, el valor en texto
       (nunca sólo el largo de la barra) y base común en 0. -->
  <ul
    v-if="filas.length"
    class="bar-list"
  >
    <li
      v-for="fila in filas"
      :key="fila.label"
      class="bar-list__fila"
    >
      <div class="bar-list__texto">
        <span class="bar-list__label">
          {{ fila.label }}
          <span
            v-if="fila.detalle"
            class="bar-list__detalle"
          >{{ fila.detalle }}</span>
        </span>
        <span class="bar-list__valor text-mono">{{ fila.texto }}</span>
      </div>
      <div
        class="bar-list__pista"
        aria-hidden="true"
      >
        <div
          class="bar-list__barra"
          :style="{ width: `${ancho(fila.valor)}%` }"
        />
      </div>
    </li>
  </ul>
  <p
    v-else
    class="bar-list__vacio"
  >
    {{ vacio }}
  </p>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  // [{ label, valor (número), texto (ya formateado), detalle? }]
  filas: {
    type: Array,
    required: true
  },
  vacio: {
    type: String,
    default: 'Sin datos en este período.'
  }
})

const maximo = computed(() => Math.max(0, ...props.filas.map((f) => f.valor)))

// Un valor positivo nunca queda invisible: mínimo 2%.
function ancho (valor) {
  if (!maximo.value || valor <= 0) return 0
  return Math.max(2, (valor / maximo.value) * 100)
}
</script>

<style lang="scss" scoped>
.bar-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.bar-list__texto {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 5px;
  font-size: 13px;
}

.bar-list__label {
  min-width: 0;
  font-weight: 600;
  color: var(--app-ink);
  overflow-wrap: anywhere;
}

.bar-list__detalle {
  margin-left: 6px;
  font-size: 12px;
  font-weight: 500;
  color: var(--app-ink-2);
}

.bar-list__valor {
  color: var(--app-ink-2);
  white-space: nowrap;
}

.bar-list__pista {
  height: 8px;
  border-radius: 4px;
  background: var(--app-page);
}

.bar-list__barra {
  height: 100%;
  border-radius: 4px;
  background: var(--q-primary);
  transition: width 0.25s;
}

.bar-list__vacio {
  margin: 0;
  font-size: 13px;
  color: var(--app-ink-2);
}
</style>
