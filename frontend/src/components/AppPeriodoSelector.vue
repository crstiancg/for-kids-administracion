<template>
  <div class="periodo">
    <div
      class="periodo__opciones"
      role="radiogroup"
      aria-label="Período"
    >
      <button
        v-for="op in OPCIONES"
        :key="op.value"
        type="button"
        role="radio"
        :aria-checked="String(model.periodo === op.value)"
        :class="['periodo__opcion', { 'periodo__opcion--activa': model.periodo === op.value }]"
        @click="elegir(op.value)"
      >
        {{ op.label }}
      </button>
    </div>

    <!-- Rango: dos fechas, se aplica al cambiar cualquiera. -->
    <div
      v-if="model.periodo === 'rango'"
      class="periodo__rango"
    >
      <input
        :value="model.desde"
        type="date"
        aria-label="Desde"
        :max="model.hasta || undefined"
        class="periodo__fecha"
        @change="cambiarFecha('desde', $event.target.value)"
      >
      <span>a</span>
      <input
        :value="model.hasta"
        type="date"
        aria-label="Hasta"
        :min="model.desde || undefined"
        class="periodo__fecha"
        @change="cambiarFecha('hasta', $event.target.value)"
      >
    </div>
  </div>
</template>

<script>
export const OPCIONES = [
  { value: 'hoy', label: 'Hoy' },
  { value: 'semana', label: '7 días' },
  { value: 'mes', label: 'Este mes' },
  { value: 'rango', label: 'Rango' }
]

// Etiqueta corta para títulos: "hoy", "los últimos 7 días"…
export function describirPeriodo (periodo) {
  if (!periodo) return ''
  if (periodo.tipo === 'hoy') return 'hoy'
  if (periodo.tipo === 'semana') return 'los últimos 7 días'
  if (periodo.tipo === 'mes') return 'este mes'
  const f = new Intl.DateTimeFormat('es-PE', { day: 'numeric', month: 'short' })
  const aFecha = (iso) => new Date(`${iso}T12:00:00`)
  return `${f.format(aFecha(periodo.desde))} – ${f.format(aFecha(periodo.hasta))}`
}
</script>

<script setup>
/**
 * Hoy / 7 días / Este mes / Rango. El modelo es lo que se manda como query
 * a la API: { periodo, desde, hasta }.
 */
const model = defineModel({ type: Object, required: true })

function hoyIso () {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function elegir (periodo) {
  if (periodo === 'rango') {
    // Arranca con el último mes: se ajusta desde ahí.
    const hasta = model.value.hasta || hoyIso()
    const d = new Date(`${hasta}T12:00:00`)
    d.setDate(d.getDate() - 29)
    const desde = model.value.desde || `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
    model.value = { periodo, desde, hasta }
  } else {
    model.value = { periodo }
  }
}

function cambiarFecha (campo, valor) {
  if (!valor) return
  model.value = { ...model.value, [campo]: valor }
}
</script>

<style lang="scss" scoped>
.periodo {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}

.periodo__opciones {
  display: inline-flex;
  padding: 3px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 10px;
  background: var(--app-surface);
}

.periodo__opcion {
  padding: 6px 12px;
  border: 0;
  border-radius: 7px;
  background: none;
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink-2);
  cursor: pointer;

  &:hover {
    color: var(--app-ink);
  }

  &--activa {
    background: var(--app-brand-soft);
    color: var(--app-brand-soft-ink);
  }
}

.periodo__rango {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--app-ink-2);
}

.periodo__fecha {
  height: 34px;
  padding: 0 8px;
  border: 1px solid var(--app-border-control);
  border-radius: 8px;
  background: var(--app-surface);
  font: inherit;
  font-size: 13px;
  color: var(--app-ink);
  color-scheme: light dark;
}
</style>
