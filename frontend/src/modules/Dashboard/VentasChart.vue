<template>
  <figure class="ventas-chart">
    <figcaption class="ventas-chart__titulo">
      {{ titulo }}
      <span class="ventas-chart__total text-mono">{{ formatearPrecio(total) }}</span>
    </figcaption>

    <!-- Una sola serie: un color (el de marca), sin leyenda; el título la
         nombra. Barras con base común en 0 y la escala desde el máximo. -->
    <div
      :class="['ventas-chart__plot', { 'ventas-chart__plot--conHoy': hoy }]"
      aria-hidden="true"
    >
      <div
        v-for="dia in serie"
        :key="dia.fecha"
        class="ventas-chart__col"
      >
        <!-- El área de hover es la columna entera, no sólo la barra: un día
             de S/ 5 también se puede señalar. -->
        <div
          class="ventas-chart__bar"
          :class="{ 'ventas-chart__bar--hoy': dia.fecha === hoy }"
          :style="{ height: `${alto(dia.total)}%` }"
        />
        <q-tooltip
          anchor="top middle"
          self="bottom middle"
          :offset="[0, 6]"
        >
          <div class="ventas-chart__tip">
            <strong>{{ formatearDia(dia.fecha) }}</strong>
            <span class="text-mono">{{ formatearPrecio(dia.total) }}</span>
            <span>{{ dia.cantidad }} {{ dia.cantidad === 1 ? 'venta' : 'ventas' }}</span>
          </div>
        </q-tooltip>
      </div>
    </div>

    <div
      class="ventas-chart__eje"
      aria-hidden="true"
    >
      <span>{{ formatearCorto(serie[0]?.fecha) }}</span>
      <span>{{ ultimo === hoyIso() ? 'Hoy' : formatearCorto(ultimo) }}</span>
    </div>

    <!-- La misma serie como tabla para lectores de pantalla. Envuelta en un
         div: una <table> ignora height/overflow (su alto es sólo un mínimo)
         y, aunque recortada, estiraba la página con un scroll al vacío. -->
    <div class="visually-hidden">
      <table>
        <caption>{{ titulo }}</caption>
        <thead>
          <tr>
            <th>Día</th>
            <th>Total</th>
            <th>Ventas</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="dia in serie"
            :key="dia.fecha"
          >
            <td>{{ formatearDia(dia.fecha) }}</td>
            <td>{{ formatearPrecio(dia.total) }}</td>
            <td>{{ dia.cantidad }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </figure>
</template>

<script setup>
import { computed } from 'vue'
import { formatearPrecio } from '@/utils/moneda'

const props = defineProps({
  // [{ fecha: 'YYYY-MM-DD', total, cantidad }], del más viejo a hoy.
  serie: {
    type: Array,
    required: true
  },
  titulo: {
    type: String,
    default: 'Ventas por día'
  }
})

const ultimo = computed(() => props.serie.at(-1)?.fecha)
// Se resalta HOY sólo si está en la serie (un rango puede terminar antes).
const hoy = computed(() => (ultimo.value === hoyIso() ? ultimo.value : null))

function hoyIso () {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
const total = computed(() => props.serie.reduce((s, d) => s + d.total, 0))
const maximo = computed(() => Math.max(...props.serie.map((d) => d.total), 0))

// Un día con ventas nunca queda invisible: mínimo 2% de alto.
function alto (valor) {
  if (!maximo.value || !valor) return 0
  return Math.max(2, (valor / maximo.value) * 100)
}

// Las fechas vienen como día del negocio: se arman a mediodía local para que
// ningún huso las corra al día anterior.
const aFecha = (iso) => new Date(`${iso}T12:00:00`)
const formatoDia = new Intl.DateTimeFormat('es-PE', { weekday: 'short', day: 'numeric', month: 'short' })
const formatoCorto = new Intl.DateTimeFormat('es-PE', { day: 'numeric', month: 'short' })

function formatearDia (iso) {
  return iso ? formatoDia.format(aFecha(iso)) : ''
}

function formatearCorto (iso) {
  return iso ? formatoCorto.format(aFecha(iso)) : ''
}
</script>

<style lang="scss" scoped>
.ventas-chart {
  // Contiene a la tabla oculta (absolute) dentro del gráfico.
  position: relative;
  margin: 0;
}

.ventas-chart__titulo {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
  font-size: 15px;
  font-weight: 700;
  color: var(--app-ink);
}

.ventas-chart__total {
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink-2);
}

.ventas-chart__plot {
  display: flex;
  align-items: flex-end;
  // 2px de superficie entre barras vecinas.
  gap: 2px;
  height: 160px;
  // Línea base recesiva.
  border-bottom: 1px solid var(--app-border-control);
}

.ventas-chart__col {
  display: flex;
  flex: 1;
  align-items: flex-end;
  height: 100%;
  cursor: default;
}

.ventas-chart__bar {
  width: 100%;
  // Extremo de dato redondeado; la base queda recta sobre el eje.
  border-radius: 4px 4px 0 0;
  background: var(--q-primary);
  transition: opacity 0.12s;
}

// Con hoy en la serie, el resto se atenúa y hoy resalta.
.ventas-chart__plot--conHoy .ventas-chart__bar:not(.ventas-chart__bar--hoy) {
  opacity: 0.55;
}

// Misma especificidad y después: el día señalado siempre se ve pleno.
.ventas-chart__plot .ventas-chart__col:hover .ventas-chart__bar {
  opacity: 1;
}

.ventas-chart__eje {
  display: flex;
  justify-content: space-between;
  margin-top: 6px;
  font-size: 11.5px;
  color: var(--app-ink-2);
}

.ventas-chart__tip {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 12px;
}

.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  margin: -1px;
  padding: 0;
  border: 0;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}
</style>
