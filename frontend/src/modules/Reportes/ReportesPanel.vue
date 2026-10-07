<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Reportes"
      :subtitle="datos ? `Ventas de ${periodoTexto}` : ''"
    />

    <AppPeriodoSelector
      v-model="filtro"
      class="rep__periodo"
    />

    <div
      v-if="cargando && !datos"
      class="rep__cargando"
    >
      <q-spinner size="28px" />
    </div>

    <div
      v-else-if="datos"
      :class="['rep__contenido', { 'rep__contenido--cargando': cargando }]"
    >
      <div class="rep__tiles">
        <AppStatTile v-bind="tileVentas" />
        <AppStatTile
          v-if="datos.ganancia"
          label="Ganancia"
          :value="formatearPrecio(datos.ganancia.monto)"
          :delta="`${datos.ganancia.margen}%`"
          delta-caption="margen"
          :trend="datos.ganancia.monto >= 0 ? 'up' : 'down'"
          :trend-is-good="datos.ganancia.monto >= 0"
        />
        <AppStatTile
          :label="`Ticket promedio · ${datos.resumen.cantidad} ventas`"
          :value="formatearPrecio(datos.resumen.ticket_promedio)"
        />
      </div>

      <div class="rep__grid">
        <AppCard class="rep__card">
          <h2 class="rep__titulo">
            Por canal
          </h2>
          <AppBarList :filas="filasCanal" />
        </AppCard>

        <AppCard class="rep__card">
          <h2 class="rep__titulo">
            Por categoría
          </h2>
          <AppBarList :filas="filasCategoria" />
        </AppCard>

        <AppCard class="rep__card">
          <h2 class="rep__titulo">
            Por cajero <span class="rep__hint">quién registró la venta</span>
          </h2>
          <AppBarList :filas="filasCajero" />
        </AppCard>

        <AppCard class="rep__card">
          <h2 class="rep__titulo">
            Cobrado por método
          </h2>
          <AppBarList :filas="filasMetodo" />
        </AppCard>

        <AppCard class="rep__card">
          <h2 class="rep__titulo">
            Mejores clientes <span class="rep__hint">sin "Cliente varios"</span>
          </h2>
          <ol
            v-if="datos.mejores_clientes.length"
            class="rep__lista"
          >
            <li
              v-for="(c, i) in datos.mejores_clientes"
              :key="c.id"
            >
              <span class="rep__pos text-mono">{{ i + 1 }}</span>
              <span class="rep__nombre">
                {{ c.nombre }}
                <span class="rep__hint">{{ c.cantidad }} {{ c.cantidad === 1 ? 'compra' : 'compras' }}</span>
              </span>
              <span class="rep__dato text-mono">{{ formatearPrecio(c.total) }}</span>
            </li>
          </ol>
          <p
            v-else
            class="rep__vacio"
          >
            Ninguna venta con cliente identificado en este período.
          </p>
        </AppCard>

        <AppCard class="rep__card">
          <h2 class="rep__titulo">
            Ofertas vigentes <span class="rep__hint">ahora</span>
          </h2>
          <ul
            v-if="datos.ofertas_activas.length"
            class="rep__lista"
          >
            <li
              v-for="o in datos.ofertas_activas"
              :key="o.id"
            >
              <span class="rep__etiqueta text-mono">{{ o.etiqueta }}</span>
              <span class="rep__nombre">
                {{ o.nombre }}
                <span class="rep__hint">{{ o.aplica_a }}</span>
              </span>
              <span class="rep__dato">vence {{ formatearFecha(o.termina_at) }}</span>
            </li>
          </ul>
          <p
            v-else
            class="rep__vacio"
          >
            No hay ofertas vigentes.
          </p>
        </AppCard>

        <!-- Sólo con permiso de ver costos (lo decide el backend). -->
        <AppCard
          v-if="datos.valor_inventario"
          class="rep__card"
        >
          <h2 class="rep__titulo">
            Valor del inventario <span class="rep__hint">ahora</span>
          </h2>
          <dl class="rep__valores">
            <div>
              <dt>Al costo</dt>
              <dd class="text-mono">
                {{ formatearPrecio(datos.valor_inventario.costo) }}
              </dd>
            </div>
            <div>
              <dt>A precio de venta</dt>
              <dd class="text-mono">
                {{ formatearPrecio(datos.valor_inventario.venta) }}
              </dd>
            </div>
            <div>
              <dt>Ganancia si se vende todo</dt>
              <dd class="text-mono">
                {{ formatearPrecio(datos.valor_inventario.ganancia_potencial) }}
              </dd>
            </div>
            <div>
              <dt>Unidades en stock</dt>
              <dd class="text-mono">
                {{ datos.valor_inventario.unidades }}
              </dd>
            </div>
          </dl>
          <p
            v-if="datos.valor_inventario.unidades_sin_costo"
            class="rep__vacio"
          >
            <q-icon
              name="info"
              size="16px"
            />
            {{ datos.valor_inventario.unidades_sin_costo }} unidades sin costo cargado no suman al valor al costo.
          </p>
        </AppCard>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import AppBarList from '@/components/AppBarList.vue'
import AppCard from '@/components/AppCard.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppPeriodoSelector, { describirPeriodo } from '@/components/AppPeriodoSelector.vue'
import AppStatTile from '@/components/AppStatTile.vue'
import ReporteService from '@/services/ReporteService'
import { formatearPrecio } from '@/utils/moneda'

const filtro = ref({ periodo: 'mes' })
const datos = ref(null)
const cargando = ref(true)

// Las respuestas pueden llegar desordenadas: sólo vale la última.
let ultima = 0
async function cargar () {
  const pedido = ++ultima
  cargando.value = true
  try {
    const respuesta = await ReporteService.get(filtro.value)
    if (pedido === ultima) datos.value = respuesta
  } finally {
    if (pedido === ultima) cargando.value = false
  }
}
watch(filtro, cargar, { immediate: true, deep: true })

const periodoTexto = computed(() => describirPeriodo(datos.value?.periodo))

const formatoFecha = new Intl.DateTimeFormat('es-PE', { day: 'numeric', month: 'short' })
function formatearFecha (iso) {
  return formatoFecha.format(new Date(iso))
}

const tileVentas = computed(() => {
  const { total, anterior } = datos.value.resumen
  const base = { label: 'Ventas', value: formatearPrecio(total), featured: true }
  // Sin base de comparación no hay flecha (ni "+∞%").
  if (!anterior) return base
  const pct = Math.round(((total - anterior) / anterior) * 100)
  return { ...base, delta: `${Math.abs(pct)}%`, deltaCaption: 'vs período previo', trend: pct >= 0 ? 'up' : 'down', trendIsGood: pct >= 0 }
})

function aFilas (lista, label, { detalle } = {}) {
  return lista
    .filter((f) => f.total !== 0 || f.cantidad)
    .map((f) => ({
      label: f[label],
      detalle: detalle?.(f),
      valor: f.total,
      texto: formatearPrecio(f.total)
    }))
}

const ventas = (n) => `${n} ${n === 1 ? 'venta' : 'ventas'}`

const filasCanal = computed(() => aFilas(datos.value.por_canal, 'label', { detalle: (f) => ventas(f.cantidad) }))
const filasCategoria = computed(() => aFilas(datos.value.por_categoria, 'categoria', { detalle: (f) => `${f.unidades} unid.` }))
const filasCajero = computed(() => aFilas(datos.value.por_cajero, 'cajero', { detalle: (f) => ventas(f.cantidad) }))
const filasMetodo = computed(() => aFilas(datos.value.por_metodo, 'label', { detalle: (f) => `${f.cantidad} ${f.cantidad === 1 ? 'pago' : 'pagos'}` })
  .sort((a, b) => b.valor - a.valor))
</script>

<style lang="scss" scoped>
.rep__periodo {
  margin-bottom: 16px;
}

.rep__cargando {
  display: flex;
  justify-content: center;
  padding: 48px;
}

.rep__contenido {
  transition: opacity 0.15s;

  &--cargando {
    opacity: 0.55;
  }
}

.rep__tiles {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 14px;
  margin-bottom: 16px;
}

.rep__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 16px;
}

.rep__card {
  padding: 20px;
}

.rep__titulo {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 8px;
  margin: 0 0 14px;
  font-size: 15px;
  font-weight: 700;
  line-height: 1.3;
  color: var(--app-ink);
}

.rep__hint {
  font-size: 12px;
  font-weight: 500;
  color: var(--app-ink-2);
}

.rep__lista {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;

  li {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13.5px;
  }
}

.rep__pos {
  width: 18px;
  font-size: 12px;
  color: var(--app-ink-2);
}

.rep__nombre {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
  font-weight: 600;
  color: var(--app-ink);
  overflow-wrap: anywhere;
}

.rep__dato {
  font-size: 12.5px;
  color: var(--app-ink-2);
  white-space: nowrap;
}

.rep__etiqueta {
  flex-shrink: 0;
  padding: 2px 8px;
  border-radius: 6px;
  background: var(--app-brand-soft);
  font-size: 12px;
  font-weight: 700;
  color: var(--app-brand-soft-ink);
}

.rep__valores {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin: 0;

  div {
    padding: 12px 14px;
    border-radius: 10px;
    background: var(--app-page);
  }

  dt {
    font-size: 12px;
    color: var(--app-ink-2);
  }

  dd {
    margin: 4px 0 0;
    font-size: 18px;
    font-weight: 700;
    color: var(--app-ink);
  }
}

.rep__vacio {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 10px 0 0;
  font-size: 13px;
  color: var(--app-ink-2);
}
</style>
