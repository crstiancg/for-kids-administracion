<template>
  <div class="app-list-page">
    <div class="detalle-barra">
      <router-link
        to="/productos"
        class="detalle-volver"
      >
        <q-icon name="arrow_back" />
        Productos
      </router-link>
      <ProductoSelector
        :actual="id"
        @elegir="(otro) => router.push(`/productos/${otro}`)"
      />
    </div>

    <q-inner-loading :showing="!producto" />

    <template v-if="producto">
      <!-- Encabezado propio (no AppPageHeader): lleva la portada como avatar. -->
      <header class="detalle-header">
        <img
          v-if="producto.archivos[0]"
          :src="producto.archivos[0].miniatura_url"
          alt=""
          class="detalle-avatar"
          data-test="avatar"
        >
        <span
          v-else
          class="detalle-avatar detalle-avatar--vacio"
        >
          <q-icon
            name="image"
            size="24px"
          />
        </span>

        <div class="detalle-header__texto">
          <div class="detalle-header__titulo">
            <h1>{{ producto.nombre }}</h1>
            <AppChip
              :status="producto.activo ? 'positive' : 'negative'"
              :label="producto.activo ? 'Activo' : 'Inactivo'"
            />
          </div>
          <p class="detalle-header__sub">
            {{ producto.categoria?.nombre }} · {{ producto.variantes.length }}
            {{ producto.variantes.length === 1 ? 'variante' : 'variantes' }}
          </p>
        </div>

        <div class="detalle-header__acciones">
          <router-link
            v-if="userStore.hasPermission('etiquetas.imprimir')"
            :to="{ path: '/etiquetas', query: { producto: producto.id } }"
            class="detalle-link"
          >
            <q-icon name="mdi-barcode" />
            Etiquetas
          </router-link>
          <AppButton
            v-if="userStore.hasPermission('productos.update')"
            variant="primary"
            label="Editar"
            icon="edit"
            data-test="editar"
            @click="formDialog = true"
          />
        </div>
      </header>

      <!-- Entrada / salida / ajuste de ESTE producto, cada uno con su permiso. -->
      <div
        v-if="accionesInventario.length"
        class="detalle-inventario"
      >
        <span class="detalle-inventario__label">Inventario</span>
        <AppButton
          v-for="accion in accionesInventario"
          :key="accion.tipo"
          variant="secondary"
          :label="accion.label"
          :icon="accion.icon"
          :data-test="`mov-${accion.tipo}`"
          @click="abrirMovimiento(accion.tipo)"
        />
      </div>

      <div class="detalle-grid">
        <!-- Fotos: la primera grande, el resto en tira. -->
        <section class="detalle-card detalle-fotos">
          <img
            v-if="fotoActual"
            :src="fotoActual.url"
            :alt="producto.nombre"
            class="detalle-fotos__principal"
          >
          <div
            v-else
            class="detalle-fotos__principal detalle-fotos__principal--vacia"
          >
            <q-icon
              name="image"
              size="40px"
            />
          </div>
          <div
            v-if="producto.archivos.length > 1"
            class="detalle-fotos__tira"
          >
            <button
              v-for="(foto, i) in producto.archivos"
              :key="foto.id"
              type="button"
              :class="['detalle-fotos__mini', { 'detalle-fotos__mini--activa': i === fotoIndex }]"
              :aria-label="`Ver foto ${i + 1}`"
              @click="fotoIndex = i"
            >
              <img
                :src="foto.miniatura_url"
                alt=""
              >
            </button>
          </div>
        </section>

        <section class="detalle-card detalle-resumen">
          <div class="detalle-stats">
            <div class="detalle-stat">
              <span class="detalle-stat__label">Precio base</span>
              <span class="detalle-stat__valor text-mono">{{ formatearPrecio(producto.precio) }}</span>
              <!-- Si alguna variante tiene precio propio, el rango real. -->
              <span
                v-if="rangoPrecio"
                class="detalle-stat__extra text-mono"
              >{{ rangoPrecio }}</span>
            </div>
            <div
              class="detalle-stat"
              data-test="stock-total"
            >
              <span class="detalle-stat__label">Stock total</span>
              <span class="detalle-stat__valor text-mono">{{ stockTotal }}</span>
            </div>
            <div class="detalle-stat">
              <span class="detalle-stat__label">Variantes</span>
              <span class="detalle-stat__valor text-mono">{{ producto.variantes.length }}</span>
            </div>
            <div
              class="detalle-stat"
              data-test="agotadas"
            >
              <span class="detalle-stat__label">Agotadas</span>
              <span :class="['detalle-stat__valor text-mono', { 'detalle-stat__valor--alerta': agotadas > 0 }]">{{ agotadas }}</span>
            </div>
          </div>

          <div class="detalle-descripcion">
            <h2 class="detalle-subtitulo">Descripción</h2>
            <p>{{ producto.descripcion || 'Sin descripción.' }}</p>
          </div>
        </section>
      </div>

      <section class="detalle-card">
        <h2 class="detalle-subtitulo">Variantes</h2>
        <div class="detalle-tabla">
          <table>
            <thead>
              <tr>
                <th>Talla</th>
                <th>Color</th>
                <th>SKU</th>
                <th>Código de barras</th>
                <th class="text-right">Precio</th>
                <template v-if="veCostos">
                  <th class="text-right">Costo</th>
                  <th class="text-right">Ganancia</th>
                </template>
                <th class="text-right">Stock</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="variante in producto.variantes"
                :key="variante.id"
                data-test="variante"
              >
                <td>{{ variante.talla?.nombre }}</td>
                <td>
                  <span class="detalle-color">
                    <span
                      class="detalle-color__muestra"
                      :style="{ background: variante.color?.hexadecimal }"
                    />
                    {{ variante.color?.nombre }}
                  </span>
                </td>
                <td class="text-mono">{{ variante.sku }}</td>
                <td class="text-mono">{{ variante.codigo_barras }}</td>
                <!-- Sin precio propio vale el base del producto. -->
                <td class="text-right text-mono">{{ formatearPrecio(variante.precio ?? producto.precio) }}</td>
                <template v-if="veCostos">
                  <td class="text-right text-mono">
                    {{ variante.costo_promedio != null ? formatearPrecio(variante.costo_promedio) : '—' }}
                  </td>
                  <td :class="['text-right text-mono', { 'detalle-agotada': ganancia(variante)?.monto < 0 }]">
                    <template v-if="ganancia(variante)">
                      {{ formatearPrecio(ganancia(variante).monto) }}
                      <span class="detalle-margen">{{ ganancia(variante).porcentaje }}%</span>
                    </template>
                    <template v-else>—</template>
                  </td>
                </template>
                <td :class="['text-right text-mono', { 'detalle-agotada': variante.stock <= 0 }]">{{ variante.stock }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- El libro de inventario del producto: de dónde salió cada unidad. -->
      <section
        v-if="userStore.hasPermission('inventario.index')"
        class="detalle-movimientos"
      >
        <h2 class="detalle-subtitulo">Movimientos de inventario</h2>
        <MovimientosProducto
          ref="movimientosRef"
          :key="producto.id"
          :producto-id="producto.id"
        />
      </section>
    </template>

    <AppDialog
      v-model="movDialog"
      :title="tipoMovimiento ? `${TIPOS[tipoMovimiento].titulo} · ${producto?.nombre ?? ''}` : ''"
      size="lg"
      persistent
    >
      <MovimientoForm
        v-if="tipoMovimiento"
        ref="movFormRef"
        :key="`${tipoMovimiento}-${aperturas}`"
        :tipo="tipoMovimiento"
        :producto-id="producto?.id"
        @save="movimientoGuardado"
      />

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="movDialog = false"
        />
        <AppButton
          variant="primary"
          label="Registrar"
          :loading="movFormRef?.form?.processing"
          @click="movFormRef.submit()"
        />
      </template>
    </AppDialog>

    <AppDialog
      v-model="formDialog"
      :title="`Editar ${producto?.nombre ?? ''}`"
      size="lg"
      persistent
    >
      <ProductosForm
        :id="id"
        ref="formRef"
        @save="guardado"
      />

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="formDialog = false"
        />
        <AppButton
          variant="primary"
          label="Guardar"
          :loading="formRef?.form.processing"
          @click="formRef.submit()"
        />
      </template>
    </AppDialog>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { useRouter } from 'vue-router'
import AppButton from '@/components/AppButton.vue'
import AppChip from '@/components/AppChip.vue'
import AppDialog from '@/components/AppDialog.vue'
import MovimientoForm from '@/modules/Inventario/MovimientoForm.vue'
import MovimientosProducto from '@/modules/Inventario/MovimientosProducto.vue'
import { TIPOS } from '@/modules/Inventario/constantes'
import ProductoService from '@/services/ProductoService'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'
import ProductoSelector from './ProductoSelector.vue'
import ProductosForm from './ProductosForm.vue'

const props = defineProps({
  id: {
    type: [Number, String],
    required: true
  }
})

const $q = useQuasar()
const router = useRouter()
const userStore = useUserStore()

const producto = ref(null)
const fotoIndex = ref(0)

async function cargar () {
  producto.value = await ProductoService.get(props.id)
  fotoIndex.value = 0
}
watch(() => props.id, cargar, { immediate: true })

const fotoActual = computed(() => producto.value?.archivos[fotoIndex.value] ?? null)
const stockTotal = computed(() => producto.value.variantes.reduce((total, v) => total + Number(v.stock), 0))
const agotadas = computed(() => producto.value.variantes.filter((v) => v.stock <= 0).length)

// El backend manda el costo sólo a quien ve inventario: si no vino, no hay
// columnas de costo ni ganancia.
const veCostos = computed(() => producto.value.variantes.some((v) => 'costo_promedio' in v))

// Ganancia por unidad al precio de lista (sin ofertas) y su margen sobre el
// precio. null sin costo: todavía no entró por una compra.
function ganancia (variante) {
  if (variante.costo_promedio == null) return null
  const precio = Number(variante.precio ?? producto.value.precio)
  const monto = precio - Number(variante.costo_promedio)
  return { monto, porcentaje: precio > 0 ? Math.round((monto / precio) * 100) : 0 }
}

// "S/ 25.00 – 29.00" sólo cuando alguna variante se sale del precio base.
const rangoPrecio = computed(() => {
  const precios = producto.value.variantes.map((v) => Number(v.precio ?? producto.value.precio))
  if (!precios.length) return null
  const min = Math.min(...precios)
  const max = Math.max(...precios)
  return min === max ? null : `${formatearPrecio(min)} – ${formatearPrecio(max)}`
})

// ── Entrada / salida / ajuste de este producto ──
const accionesInventario = computed(() => Object.entries(TIPOS)
  .filter(([, tipo]) => userStore.hasPermission(tipo.permiso))
  .map(([clave, tipo]) => ({ tipo: clave, ...tipo })))

const movDialog = ref(false)
const movFormRef = ref()
const tipoMovimiento = ref(null)
// Cada apertura monta un form nuevo, recargado con el stock de ese momento.
const aperturas = ref(0)

function abrirMovimiento (tipo) {
  tipoMovimiento.value = tipo
  aperturas.value++
  movDialog.value = true
}

function movimientoGuardado (movimientos) {
  movDialog.value = false
  cargar()
  movimientosRef.value?.recargar()

  const n = movimientos.length
  $q.notify({
    type: 'positive',
    message: n === 0
      ? 'El conteo coincide con el sistema: no hubo diferencias que registrar.'
      : `${n} ${n === 1 ? 'movimiento registrado' : 'movimientos registrados'}.`,
    position: 'top-right',
    timeout: 2500
  })
}

// ── Editar en el mismo diálogo que el listado ──
const formDialog = ref(false)
const formRef = ref()
const movimientosRef = ref()

// Al editar se pueden quitar variantes: stock y movimientos se releen juntos.
function guardado () {
  formDialog.value = false
  cargar()
  movimientosRef.value?.recargar()
}
</script>

<style lang="scss" scoped>
.detalle-volver,
.detalle-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink-2);
  text-decoration: none;

  &:hover {
    color: var(--app-ink);
  }
}

.detalle-barra {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 18px;
}

.detalle-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 16px;
  margin-bottom: 16px;

  &__texto {
    min-width: 0;
  }

  &__titulo {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;

    h1 {
      margin: 0;
      font-size: 25px;
      font-weight: 700;
      line-height: 1.2;
      letter-spacing: -0.3px;
      color: var(--app-ink);
      overflow-wrap: anywhere;
    }
  }

  &__sub {
    margin: 4px 0 0;
    font-size: 13.5px;
    color: var(--app-ink-2);
  }

  &__acciones {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-left: auto;
  }
}

.detalle-avatar {
  flex-shrink: 0;
  width: 64px;
  height: 64px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 14px;
  object-fit: cover;

  &--vacio {
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--app-page);
    color: var(--app-ink-2);
  }
}

.detalle-inventario {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
  padding: 12px 16px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 14px;
  background: var(--app-surface);

  &__label {
    margin-right: 4px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.7px;
    text-transform: uppercase;
    color: var(--app-ink-2);
  }
}

.detalle-grid {
  display: grid;
  grid-template-columns: minmax(0, 340px) minmax(0, 1fr);
  gap: 16px;
  margin-bottom: 16px;

  @media (max-width: 767px) {
    grid-template-columns: minmax(0, 1fr);
  }
}

.detalle-card {
  padding: 20px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 14px;
  background: var(--app-surface);
}

.detalle-fotos__principal {
  display: block;
  width: 100%;
  aspect-ratio: 1;
  border-radius: 10px;
  object-fit: cover;

  &--vacia {
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--app-page);
    color: var(--app-ink-2);
  }
}

.detalle-fotos__tira {
  display: flex;
  gap: 8px;
  margin-top: 10px;
  overflow-x: auto;
}

.detalle-fotos__mini {
  flex-shrink: 0;
  width: 52px;
  height: 52px;
  padding: 0;
  border: 2px solid transparent;
  border-radius: 8px;
  background: none;
  cursor: pointer;
  overflow: hidden;

  &--activa {
    border-color: var(--q-primary);
  }

  img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
}

.detalle-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 12px;
}

.detalle-stat {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px 14px;
  border-radius: 10px;
  background: var(--app-page);

  &__label {
    font-size: 12px;
    color: var(--app-ink-2);
  }

  &__valor {
    font-size: 20px;
    font-weight: 700;
    color: var(--app-ink);

    &--alerta {
      color: var(--q-negative);
    }
  }

  &__extra {
    font-size: 12px;
    color: var(--app-ink-2);
  }
}

.detalle-descripcion {
  margin-top: 20px;

  p {
    margin: 0;
    font-size: 14px;
    line-height: 1.6;
    color: var(--app-ink-2);
    white-space: pre-line;
  }
}

.detalle-subtitulo {
  margin: 0 0 10px;
  font-size: 15px;
  font-weight: 700;
  line-height: 1.3;
  color: var(--app-ink);
}

.detalle-tabla {
  overflow-x: auto;

  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
  }

  th {
    padding: 10px 12px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-align: left;
    text-transform: uppercase;
    color: var(--app-ink-2);
    border-bottom: 1px solid var(--app-border-subtle);
  }

  td {
    padding: 10px 12px;
    color: var(--app-ink);
    border-bottom: 1px solid var(--app-border-subtle);
  }

  tr:last-child td {
    border-bottom: 0;
  }

  .text-right {
    text-align: right;
  }
}

.detalle-movimientos {
  margin-top: 24px;
}

.detalle-color {
  display: inline-flex;
  align-items: center;
  gap: 8px;

  &__muestra {
    width: 14px;
    height: 14px;
    border: 1px solid var(--app-border-control);
    border-radius: 999px;
  }
}

.detalle-margen {
  margin-left: 4px;
  font-size: 11.5px;
  color: var(--app-ink-2);
}

.detalle-agotada {
  color: var(--q-negative);
  font-weight: 700;
}
</style>
