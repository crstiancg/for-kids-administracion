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

    <div
      v-if="cargando"
      class="dash__cargando"
    >
      <q-spinner size="28px" />
    </div>

    <template v-else-if="datos">
      <!-- ── Cifras del día. Ventas es la hero: una sola por pantalla. ── -->
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

      <AppCard
        v-if="datos.ventas"
        class="dash__card"
      >
        <VentasChart :serie="datos.ventas.serie" />
      </AppCard>

      <div class="dash__grid">
        <!-- ── Más vendidos ── -->
        <AppCard
          v-if="datos.ventas"
          class="dash__card"
        >
          <h2 class="dash__titulo">
            Más vendidos · últimos 7 días
          </h2>
          <ol
            v-if="datos.ventas.top_semana.length"
            class="dash__lista"
          >
            <li
              v-for="(p, i) in datos.ventas.top_semana"
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
            Todavía no hay ventas esta semana.
          </p>
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

        <!-- ── Pedidos pendientes ── -->
        <AppCard
          v-if="datos.pendientes !== undefined"
          class="dash__card dash__card--accion"
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

      <p
        v-if="!tiles.length && !datos.ventas"
        class="dash__vacio"
      >
        No tenés módulos con información para mostrar acá.
      </p>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import AppButton from '@/components/AppButton.vue'
import AppCard from '@/components/AppCard.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
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

const datos = ref(null)
const cargando = ref(true)

onMounted(async () => {
  try {
    datos.value = await DashboardService.get()
  } finally {
    cargando.value = false
  }
})

const primerNombre = computed(() => (userStore.name ?? '').split(' ')[0])
const fechaHoy = computed(() => {
  const texto = new Intl.DateTimeFormat('es-PE', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date())
  return texto.charAt(0).toUpperCase() + texto.slice(1)
})

const formatoHora = new Intl.DateTimeFormat('es-PE', { hour: '2-digit', minute: '2-digit' })
function formatearHora (iso) {
  return formatoHora.format(new Date(iso))
}

// % contra ayer. Sin ventas ayer no hay base: no se inventa un "+∞%".
function variacion (hoy, ayer) {
  if (!ayer) return null
  return Math.round(((hoy - ayer) / ayer) * 100)
}

const tiles = computed(() => {
  const d = datos.value
  if (!d) return []
  const lista = []

  if (d.ventas) {
    const pct = variacion(d.ventas.hoy, d.ventas.ayer)
    lista.push({
      label: 'Ventas de hoy',
      value: formatearPrecio(d.ventas.hoy),
      featured: true,
      // La flecha sólo cuando hay contra qué comparar.
      ...(pct === null
        ? {}
        : { delta: `${Math.abs(pct)}%`, deltaCaption: 'vs ayer', trend: pct >= 0 ? 'up' : 'down', trendIsGood: pct >= 0 })
    })
  }

  if (d.ganancia) {
    lista.push({
      label: 'Ganancia de hoy',
      value: formatearPrecio(d.ganancia.hoy.monto),
      delta: `${d.ganancia.hoy.margen}%`,
      deltaCaption: `margen · mes ${formatearPrecio(d.ganancia.mes.monto)}`,
      trend: d.ganancia.hoy.monto >= 0 ? 'up' : 'down',
      trendIsGood: d.ganancia.hoy.monto >= 0
    })
  }

  if (d.ventas) {
    lista.push({
      label: `Ticket promedio · ${d.ventas.cantidad_hoy} ${d.ventas.cantidad_hoy === 1 ? 'venta' : 'ventas'}`,
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
</script>

<style lang="scss" scoped>
.dash__cargando {
  display: flex;
  justify-content: center;
  padding: 48px;
}

.dash__tiles {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 14px;
  margin-bottom: 16px;
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

.dash__link {
  display: inline-block;
  margin-top: 12px;
  font-size: 13px;
  font-weight: 600;
  color: var(--q-primary);
  text-decoration: none;
}
</style>
