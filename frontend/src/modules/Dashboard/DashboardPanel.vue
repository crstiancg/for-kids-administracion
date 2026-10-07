<template>
  <div class="app-list-page">
    <AppPageHeader
      :title="`Hola, ${primerNombre}`"
      :subtitle="fechaHoy"
    >
      <template #actions>
        <AppButton
          v-if="userStore.hasPermission('ventas.store')"
          variant="primary"
          label="Punto de venta"
          icon="point_of_sale"
          to="/pos"
        />
      </template>
    </AppPageHeader>

    <!-- Las cifras, métodos y tallas siguen al período; stock, pendientes y
         cajas son siempre "ahora". -->
    <AppPeriodoSelector
      v-if="!datos || datos.ventas || datos.ganancia"
      v-model="filtro"
      class="dash__periodo"
    />

    <div
      v-if="cargando && !datos"
      class="dash__cargando"
    >
      <q-spinner size="28px" />
    </div>

    <template v-else-if="datos">
      <div :class="['dash__contenido', { 'dash__contenido--cargando': cargando }]">
        <!-- ── Cifras. Ventas es la hero: una sola por pantalla. ── -->
        <div
          v-if="tiles.length"
          class="dash__tiles"
        >
          <AppStatTile
            v-for="tile in tiles"
            :key="tile.label"
            v-bind="tile"
          />
        </div>

        <p
          v-if="datos.ganancia?.periodo.items_sin_costo"
          class="dash__aviso"
        >
          <q-icon
            name="info"
            size="16px"
          />
          {{ datos.ganancia.periodo.items_sin_costo }} ítems vendidos no tienen costo cargado: la ganancia los deja afuera.
        </p>

        <AppCard
          v-if="datos.ventas"
          class="dash__card"
        >
          <VentasChart
            :serie="datos.ventas.serie"
            :titulo="datos.periodo.tipo === 'hoy' ? 'Ventas de los últimos 30 días' : `Ventas por día · ${periodoTexto}`"
          />
        </AppCard>

        <div class="dash__grid">
          <!-- ── Métodos de pago: lo que hay que cuadrar con el banco ── -->
          <AppCard
            v-if="datos.ventas"
            class="dash__card"
          >
            <h2 class="dash__titulo">
              Cobrado por método <span class="dash__hint">{{ periodoTexto }}</span>
            </h2>
            <AppBarList
              :filas="filasMetodos"
              vacio="Sin cobros en este período."
            />
          </AppCard>

          <!-- ── Más vendidos ── -->
          <AppCard
            v-if="datos.ventas"
            class="dash__card"
          >
            <h2 class="dash__titulo">
              Más vendidos <span class="dash__hint">{{ periodoTexto }}</span>
            </h2>
            <ol
              v-if="datos.ventas.top.length"
              class="dash__lista"
            >
              <li
                v-for="(p, i) in datos.ventas.top"
                :key="p.id"
              >
                <span class="dash__pos text-mono">{{ i + 1 }}</span>
                <router-link
                  :to="`/productos/${p.id}`"
                  class="dash__nombre"
                >
                  {{ p.nombre }}
                </router-link>
                <span class="dash__dato text-mono">{{ p.unidades }} unid.</span>
                <span class="dash__dato text-mono">{{ formatearPrecio(p.total) }}</span>
              </li>
            </ol>
            <p
              v-else
              class="dash__vacio"
            >
              Sin ventas en este período.
            </p>
          </AppCard>

          <!-- ── Tallas: la curva de talles para pedirle al proveedor ── -->
          <AppCard
            v-if="datos.ventas"
            class="dash__card"
          >
            <h2 class="dash__titulo">
              Unidades por talla <span class="dash__hint">{{ periodoTexto }}</span>
            </h2>
            <AppBarList :filas="filasTallas" />
          </AppCard>

          <!-- ── Stock bajo ── -->
          <AppCard
            v-if="datos.stock_bajo"
            class="dash__card"
          >
            <h2 class="dash__titulo">
              Para reponer
              <span class="dash__hint">menos de {{ datos.stock_bajo.umbral }} unid.</span>
            </h2>
            <ul
              v-if="datos.stock_bajo.variantes.length"
              class="dash__lista"
            >
              <li
                v-for="v in datos.stock_bajo.variantes"
                :key="v.id"
              >
                <span
                  class="dash__swatch"
                  :style="{ background: v.color.hexadecimal }"
                />
                <router-link
                  :to="`/productos/${v.producto_id}`"
                  class="dash__nombre"
                >
                  {{ v.producto }}
                  <span class="dash__hint">Talla {{ v.talla }} · {{ v.color.nombre }}</span>
                </router-link>
                <!-- Estado con ícono + texto, nunca sólo color. -->
                <span :class="['dash__stock text-mono', { 'dash__stock--agotado': v.stock <= 0 }]">
                  <q-icon
                    :name="v.stock <= 0 ? 'block' : 'warning'"
                    size="14px"
                  />
                  {{ v.stock <= 0 ? 'Agotado' : `${v.stock} unid.` }}
                </span>
              </li>
            </ul>
            <p
              v-else
              class="dash__vacio"
            >
              <q-icon
                name="check_circle"
                size="16px"
              />
              Todo con stock suficiente.
            </p>
            <p
              v-if="datos.stock_bajo.total > datos.stock_bajo.variantes.length"
              class="dash__mas"
            >
              y {{ datos.stock_bajo.total - datos.stock_bajo.variantes.length }} variantes más ·
              <router-link to="/inventario">
                ver inventario
              </router-link>
            </p>
          </AppCard>

          <!-- ── Sin movimiento: candidatos a oferta ── -->
          <AppCard
            v-if="datos.sin_movimiento"
            class="dash__card"
          >
            <h2 class="dash__titulo">
              Sin vender
              <span class="dash__hint">hace {{ datos.sin_movimiento.dias }}+ días, con stock</span>
            </h2>
            <ul
              v-if="datos.sin_movimiento.productos.length"
              class="dash__lista"
            >
              <li
                v-for="p in datos.sin_movimiento.productos"
                :key="p.id"
              >
                <router-link
                  :to="`/productos/${p.id}`"
                  class="dash__nombre"
                >
                  {{ p.nombre }}
                  <span class="dash__hint">{{ p.stock }} unid. paradas</span>
                </router-link>
                <span class="dash__dato text-mono">{{ formatearPrecio(p.valor_costo) }}</span>
              </li>
            </ul>
            <p
              v-else
              class="dash__vacio"
            >
              <q-icon
                name="check_circle"
                size="16px"
              />
              Todo lo que tiene stock se vendió este mes.
            </p>
            <p
              v-if="datos.sin_movimiento.productos.length && userStore.hasPermission('ofertas.store')"
              class="dash__mas"
            >
              <router-link to="/ofertas">
                Crear una oferta →
              </router-link>
            </p>
          </AppCard>

          <!-- ── Pedidos pendientes ── -->
          <AppCard
            v-if="datos.pendientes !== undefined"
            class="dash__card"
          >
            <h2 class="dash__titulo">
              Pedidos pendientes
            </h2>
            <div class="dash__cifra text-mono">
              {{ datos.pendientes }}
            </div>
            <p class="dash__vacio">
              {{ datos.pendientes ? 'Esperan confirmación o cobro.' : 'No hay pedidos esperando.' }}
            </p>
            <router-link
              v-if="datos.pendientes"
              to="/pedidos"
              class="dash__link"
            >
              Ver pedidos →
            </router-link>
          </AppCard>

          <!-- ── Cajas abiertas (una por cajero) ── -->
          <AppCard
            v-if="datos.cajas_abiertas"
            class="dash__card"
          >
            <h2 class="dash__titulo">
              Cajas abiertas
            </h2>
            <ul
              v-if="datos.cajas_abiertas.length"
              class="dash__lista"
            >
              <li
                v-for="c in datos.cajas_abiertas"
                :key="c.id"
              >
                <span class="dash__nombre">
                  {{ c.cajero }}
                  <span class="dash__hint">desde {{ formatearHora(c.abierta_at) }}</span>
                </span>
                <span
                  v-if="c.vencida"
                  class="dash__stock dash__stock--agotado"
                >
                  <q-icon
                    name="warning"
                    size="14px"
                  />
                  Sin cerrar
                </span>
                <span class="dash__dato text-mono">{{ formatearPrecio(c.total_cobrado) }}</span>
              </li>
            </ul>
            <p
              v-else
              class="dash__vacio"
            >
              Ninguna caja abierta ahora.
            </p>
          </AppCard>
        </div>

        <!-- ── Últimas ventas: "qué se vendió recién", sin importar el período ── -->
        <AppCard
          v-if="datos.ventas"
          class="dash__card dash__ultimas"
        >
          <h2 class="dash__titulo">
            Últimas ventas
          </h2>
          <div
            v-if="datos.ventas.ultimas.length"
            class="dash__tabla"
          >
            <table>
              <thead>
                <tr>
                  <th>Pedido</th>
                  <th>Hora</th>
                  <th>Cliente</th>
                  <th>Canal</th>
                  <th>Pago</th>
                  <th>Cajero</th>
                  <th class="text-right">
                    Unid.
                  </th>
                  <th class="text-right">
                    Total
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="v in datos.ventas.ultimas"
                  :key="v.id"
                >
                  <td class="text-mono">
                    {{ v.codigo }}
                  </td>
                  <td class="dash__hint">
                    {{ formatearMomento(v.fecha) }}
                  </td>
                  <td>{{ v.cliente ?? 'Cliente varios' }}</td>
                  <td>{{ v.canal }}</td>
                  <td>{{ v.metodos.join(' + ') || '—' }}</td>
                  <td class="dash__hint">
                    {{ v.cajero ?? '—' }}
                  </td>
                  <td class="text-right text-mono">
                    {{ v.unidades }}
                  </td>
                  <td class="text-right text-mono dash__total">
                    {{ formatearPrecio(v.total) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p
            v-else
            class="dash__vacio"
          >
            Todavía no hay ventas.
          </p>
          <router-link
            to="/pedidos"
            class="dash__link"
          >
            Ver todos los pedidos →
          </router-link>
        </AppCard>

        <p
          v-if="!tiles.length && !datos.ventas"
          class="dash__vacio"
        >
          No tenés módulos con información para mostrar acá.
        </p>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import AppBarList from '@/components/AppBarList.vue'
import AppButton from '@/components/AppButton.vue'
import AppCard from '@/components/AppCard.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppPeriodoSelector, { describirPeriodo } from '@/components/AppPeriodoSelector.vue'
import AppStatTile from '@/components/AppStatTile.vue'
import DashboardService from '@/services/DashboardService'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'
import VentasChart from './VentasChart.vue'

/**
 * El tablero de inicio. Cada bloque aparece sólo si vino en la respuesta:
 * el backend ya lo filtró por los permisos del usuario.
 */
const userStore = useUserStore()

const filtro = ref({ periodo: 'hoy' })
const datos = ref(null)
const cargando = ref(true)

// Las respuestas pueden llegar desordenadas: sólo vale la última.
let ultima = 0
async function cargar () {
  const pedido = ++ultima
  cargando.value = true
  try {
    const respuesta = await DashboardService.get(filtro.value)
    if (pedido === ultima) datos.value = respuesta
  } finally {
    if (pedido === ultima) cargando.value = false
  }
}
watch(filtro, cargar, { immediate: true, deep: true })

const primerNombre = computed(() => (userStore.name ?? '').split(' ')[0])
const fechaHoy = computed(() => {
  const texto = new Intl.DateTimeFormat('es-PE', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date())
  return texto.charAt(0).toUpperCase() + texto.slice(1)
})
const periodoTexto = computed(() => describirPeriodo(datos.value?.periodo))

const formatoHora = new Intl.DateTimeFormat('es-PE', { hour: '2-digit', minute: '2-digit' })
function formatearHora (iso) {
  return formatoHora.format(new Date(iso))
}

// "14:32" si fue hoy; "6 oct · 14:32" si no.
const formatoDiaCorto = new Intl.DateTimeFormat('es-PE', { day: 'numeric', month: 'short' })
function formatearMomento (iso) {
  if (!iso) return ''
  const fecha = new Date(iso)
  const esHoy = fecha.toDateString() === new Date().toDateString()
  return esHoy ? formatoHora.format(fecha) : `${formatoDiaCorto.format(fecha)} · ${formatoHora.format(fecha)}`
}

const COMPARACION = { hoy: 'vs ayer', semana: 'vs 7 días previos', mes: 'vs mismo tramo previo', rango: 'vs período previo' }

// % contra el período anterior. Sin base no se inventa un "+∞%".
function variacion (actual, anterior) {
  if (!anterior) return null
  return Math.round(((actual - anterior) / anterior) * 100)
}

const tiles = computed(() => {
  const d = datos.value
  if (!d) return []
  const lista = []
  const tipo = d.periodo.tipo

  if (d.ventas) {
    const pct = variacion(d.ventas.total, d.ventas.anterior)
    lista.push({
      label: tipo === 'hoy' ? 'Ventas de hoy' : `Ventas · ${periodoTexto.value}`,
      value: formatearPrecio(d.ventas.total),
      featured: true,
      // La flecha sólo cuando hay contra qué comparar.
      ...(pct === null
        ? {}
        : { delta: `${Math.abs(pct)}%`, deltaCaption: COMPARACION[tipo], trend: pct >= 0 ? 'up' : 'down', trendIsGood: pct >= 0 })
    })
  }

  if (d.ganancia) {
    lista.push({
      label: tipo === 'hoy' ? 'Ganancia de hoy' : 'Ganancia del período',
      value: formatearPrecio(d.ganancia.periodo.monto),
      delta: `${d.ganancia.periodo.margen}%`,
      deltaCaption: tipo === 'mes' ? 'margen' : `margen · mes ${formatearPrecio(d.ganancia.mes.monto)}`,
      trend: d.ganancia.periodo.monto >= 0 ? 'up' : 'down',
      trendIsGood: d.ganancia.periodo.monto >= 0
    })
  }

  if (d.ventas) {
    lista.push({
      label: `Ticket promedio · ${d.ventas.cantidad} ${d.ventas.cantidad === 1 ? 'venta' : 'ventas'}`,
      value: formatearPrecio(d.ventas.ticket_promedio)
    })
  }

  if ('mi_caja' in d) {
    const caja = d.mi_caja
    lista.push({
      label: !caja ? 'Mi caja' : caja.vencida ? 'Mi caja · de un día anterior' : 'Mi caja · efectivo en cajón',
      value: !caja ? 'Cerrada' : caja.vencida ? 'Sin cerrar' : formatearPrecio(caja.efectivo_esperado)
    })
  }

  return lista
})

const filasMetodos = computed(() => (datos.value?.ventas?.por_metodo ?? [])
  .filter((m) => m.cantidad > 0)
  .sort((a, b) => b.total - a.total)
  .map((m) => ({
    label: m.label,
    detalle: `${m.cantidad} ${m.cantidad === 1 ? 'pago' : 'pagos'}`,
    valor: m.total,
    texto: formatearPrecio(m.total)
  })))

// En el orden de las tallas (no por ranking): se lee como una curva.
const filasTallas = computed(() => (datos.value?.ventas?.por_talla ?? []).map((t) => ({
  label: `Talla ${t.talla}`,
  valor: t.unidades,
  texto: `${t.unidades} unid.`
})))
</script>

<style lang="scss" scoped>
.dash__periodo {
  margin-bottom: 16px;
}

.dash__cargando {
  display: flex;
  justify-content: center;
  padding: 48px;
}

.dash__contenido {
  transition: opacity 0.15s;

  &--cargando {
    opacity: 0.55;
  }
}

.dash__tiles {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 14px;
  margin-bottom: 16px;
}

.dash__aviso {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: -4px 0 16px;
  font-size: 12.5px;
  color: var(--app-ink-2);
}

.dash__card {
  padding: 20px;
}

.dash__card + .dash__grid,
.dash__tiles + .dash__grid {
  margin-top: 16px;
}

.dash__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 16px;
}

.dash__titulo {
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

.dash__hint {
  font-size: 12px;
  font-weight: 500;
  color: var(--app-ink-2);
}

.dash__lista {
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

.dash__pos {
  width: 18px;
  font-size: 12px;
  color: var(--app-ink-2);
}

.dash__nombre {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
  font-weight: 600;
  color: var(--app-ink);
  text-decoration: none;
  overflow-wrap: anywhere;
}

a.dash__nombre:hover {
  color: var(--q-primary);
}

.dash__dato {
  color: var(--app-ink-2);
  white-space: nowrap;
}

.dash__swatch {
  flex-shrink: 0;
  width: 14px;
  height: 14px;
  border: 1px solid var(--app-border-control);
  border-radius: 4px;
}

.dash__stock {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12.5px;
  font-weight: 600;
  white-space: nowrap;
  color: var(--q-warning);

  &--agotado {
    color: var(--q-negative);
  }
}

.dash__cifra {
  font-size: 36px;
  font-weight: 700;
  line-height: 1;
  color: var(--app-ink);
}

.dash__vacio {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 8px 0 0;
  font-size: 13px;
  color: var(--app-ink-2);
}

.dash__mas {
  margin: 12px 0 0;
  font-size: 12.5px;
  color: var(--app-ink-2);
}

.dash__ultimas {
  margin-top: 16px;
}

.dash__tabla {
  overflow-x: auto;

  table {
    width: 100%;
    min-width: 640px;
    border-collapse: collapse;
    font-size: 13px;
  }

  th {
    padding: 8px 10px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-align: left;
    text-transform: uppercase;
    color: var(--app-ink-2);
    border-bottom: 1px solid var(--app-border-subtle);
  }

  td {
    padding: 10px;
    color: var(--app-ink);
    border-bottom: 1px solid var(--app-border-subtle);
    white-space: nowrap;
  }

  tr:last-child td {
    border-bottom: 0;
  }

  .text-right {
    text-align: right;
  }
}

.dash__total {
  font-weight: 700;
}

.dash__link {
  display: inline-block;
  margin-top: 12px;
  font-size: 13px;
  font-weight: 600;
  color: var(--q-primary);
  text-decoration: none;
}
</style>
