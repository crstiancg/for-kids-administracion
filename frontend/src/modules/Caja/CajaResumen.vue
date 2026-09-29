<template>
  <div class="caja-resumen">
    <!-- ── Totales ── -->
    <div class="caja-resumen__tiles">
      <div class="caja-resumen__tile">
        <span class="caja-resumen__tileLabel">Apertura</span>
        <strong class="text-mono">{{ formatearPrecio(caja.resumen.monto_apertura) }}</strong>
      </div>
      <div class="caja-resumen__tile">
        <span class="caja-resumen__tileLabel">Cobrado (todos los métodos)</span>
        <strong class="text-mono">{{ formatearPrecio(caja.resumen.total_cobrado) }}</strong>
      </div>
      <div class="caja-resumen__tile">
        <span class="caja-resumen__tileLabel">Ingresos / egresos</span>
        <strong class="text-mono">
          +{{ formatearPrecio(caja.resumen.ingresos) }} / −{{ formatearPrecio(caja.resumen.egresos) }}
        </strong>
      </div>
      <div class="caja-resumen__tile caja-resumen__tile--destacado">
        <span class="caja-resumen__tileLabel">Efectivo esperado en caja</span>
        <strong class="text-mono">{{ formatearPrecio(caja.resumen.efectivo_esperado) }}</strong>
      </div>
    </div>

    <!-- Arqueo (sólo cerrada). -->
    <div
      v-if="caja.estado === 'cerrada'"
      :class="['caja-resumen__arqueo', claseDiferencia]"
    >
      Contado <strong class="text-mono">{{ formatearPrecio(caja.monto_contado) }}</strong>
      · esperado <span class="text-mono">{{ formatearPrecio(caja.monto_esperado) }}</span>
      · {{ textoDiferencia }}
      <div
        v-if="caja.observacion_cierre"
        class="caja-resumen__detalle"
      >
        {{ caja.observacion_cierre }}
      </div>
    </div>

    <!-- ── Por método ── -->
    <section>
      <h3 class="caja-resumen__titulo">
        Cobros por método
      </h3>
      <ul class="caja-resumen__metodos">
        <li
          v-for="cobro in caja.resumen.cobros"
          :key="cobro.metodo"
          :class="{ 'caja-resumen__metodo--vacio': !cobro.cantidad }"
        >
          <span>{{ cobro.label }} <span class="caja-resumen__detalle">({{ cobro.cantidad }})</span></span>
          <span class="text-mono">{{ formatearPrecio(cobro.total) }}</span>
        </li>
      </ul>
    </section>

    <!-- ── Movimientos del día ── -->
    <section>
      <h3 class="caja-resumen__titulo">
        Pagos y movimientos
      </h3>
      <ul
        v-if="registros.length"
        class="caja-resumen__registros"
      >
        <li
          v-for="r in registros"
          :key="r.clave"
        >
          <div>
            <div class="caja-resumen__concepto">
              {{ r.concepto }}
            </div>
            <div class="caja-resumen__detalle">
              {{ formatearHora(r.fecha) }}<template v-if="r.detalle">
                · {{ r.detalle }}
              </template><template v-if="r.usuario">
                · {{ r.usuario }}
              </template>
            </div>
          </div>
          <span :class="['text-mono', r.monto < 0 ? 'caja-resumen__menos' : 'caja-resumen__mas']">
            {{ r.monto < 0 ? '−' : '+' }}{{ formatearPrecio(Math.abs(r.monto)) }}
          </span>
        </li>
      </ul>
      <p
        v-else
        class="caja-resumen__detalle"
      >
        Todavía no hay pagos ni movimientos en esta caja.
      </p>
    </section>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { formatearPrecio } from '@/utils/moneda'

/**
 * Totales, arqueo y registros de una caja (la abierta o una del historial).
 * Sólo muestra: las acciones las pone la pantalla que lo usa.
 */
const props = defineProps({
  // CajaResource con `resumen`, `pagos` y `movimientos`.
  caja: {
    type: Object,
    required: true
  }
})

const formatoHora = new Intl.DateTimeFormat('es-PE', { hour: '2-digit', minute: '2-digit' })
function formatearHora (iso) {
  return iso ? formatoHora.format(new Date(iso)) : ''
}

// Pagos y movimientos en una sola lista, del más nuevo al más viejo.
const registros = computed(() => [
  ...(props.caja.pagos ?? []).map((p) => ({
    clave: `p${p.id}`,
    fecha: p.fecha,
    monto: Number(p.monto),
    concepto: `${p.es_devolucion ? 'Devolución' : 'Cobro'} ${p.pedido?.codigo ?? ''} · ${p.metodo_label}`,
    detalle: [p.referencia && `Op. ${p.referencia}`, p.vuelto && Number(p.vuelto) > 0 && `vuelto ${formatearPrecio(p.vuelto)}`, p.motivo]
      .filter(Boolean).join(' · '),
    usuario: p.usuario?.name
  })),
  ...(props.caja.movimientos ?? []).map((m) => ({
    clave: `m${m.id}`,
    fecha: m.fecha,
    monto: m.tipo === 'egreso' ? -Number(m.monto) : Number(m.monto),
    concepto: `${m.tipo === 'egreso' ? 'Egreso' : 'Ingreso'} · ${m.concepto}`,
    detalle: '',
    usuario: m.usuario?.name
  }))
].sort((a, b) => b.fecha.localeCompare(a.fecha)))

const diferencia = computed(() => Number(props.caja.diferencia ?? 0))
const claseDiferencia = computed(() => {
  if (diferencia.value < 0) return 'caja-resumen__arqueo--faltante'
  if (diferencia.value > 0) return 'caja-resumen__arqueo--sobrante'
  return 'caja-resumen__arqueo--cuadra'
})
const textoDiferencia = computed(() => {
  if (diferencia.value < 0) return `faltante de ${formatearPrecio(-diferencia.value)}`
  if (diferencia.value > 0) return `sobrante de ${formatearPrecio(diferencia.value)}`
  return 'cuadra exacto'
})
</script>

<style lang="scss" scoped>
.caja-resumen {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.caja-resumen__tiles {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 12px;
}

.caja-resumen__tile {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 14px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 12px;
  background: var(--app-surface);

  strong {
    font-size: 18px;
    color: var(--app-ink);
  }

  &--destacado {
    border-color: $primary;
    background: rgba($primary, 0.06);

    strong {
      font-size: 22px;
    }
  }
}

.caja-resumen__tileLabel {
  font-size: 12px;
  color: var(--app-ink-2);
}

.caja-resumen__arqueo {
  padding: 12px 14px;
  border-radius: 10px;
  font-size: 13.5px;
  color: var(--app-ink);

  &--cuadra {
    background: rgba($positive, 0.1);
  }

  &--sobrante {
    background: rgba($warning, 0.14);
  }

  &--faltante {
    background: rgba($negative, 0.1);
  }
}

.caja-resumen__titulo {
  margin: 0 0 8px;
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);
}

.caja-resumen__metodos,
.caja-resumen__registros {
  margin: 0;
  padding: 0;
  list-style: none;

  li {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 0;
    border-top: 1px solid var(--app-border-subtle);
    font-size: 13px;
    color: var(--app-ink);
  }
}

.caja-resumen__metodo--vacio {
  color: var(--app-ink-2) !important;
}

.caja-resumen__concepto {
  font-weight: 600;
}

.caja-resumen__detalle {
  font-size: 12px;
  color: var(--app-ink-2);
}

.caja-resumen__mas {
  font-weight: 600;
  color: var(--q-positive);
}

.caja-resumen__menos {
  font-weight: 600;
  color: var(--q-negative);
}
</style>
