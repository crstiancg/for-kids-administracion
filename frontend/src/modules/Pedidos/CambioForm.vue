<template>
  <div
    v-if="!datos"
    class="cambio__cargando"
  >
    <q-spinner size="24px" />
  </div>

  <!-- Fuera de plazo o no entregado: se explica, no se ofrece el form. -->
  <div
    v-else-if="!datos.cambiable"
    class="cambio__bloqueado"
    role="alert"
  >
    <q-icon
      name="block"
      size="22px"
    />
    <span v-if="datos.pedido.estado !== 'entregado'">Sólo se cambia lo de una venta entregada.</span>
    <span v-else>
      Pasaron {{ datos.dias_desde_entrega }} días de la compra: el plazo para cambios es de {{ datos.plazo_dias }}.
    </span>
  </div>

  <form
    v-else
    class="cambio"
    novalidate
    @submit.prevent="submit"
  >
    <p class="cambio__ayuda">
      Lo que vuelve queda como <strong>saldo a favor</strong> del cliente (no se devuelve efectivo) y se usa
      primero para pagar lo que se lleva. Plazo: {{ datos.plazo_dias }} días · van {{ datos.dias_desde_entrega }}.
    </p>

    <!-- ── 1. Qué vuelve ── -->
    <section>
      <h3 class="cambio__titulo">
        1. ¿Qué vuelve?
      </h3>
      <ul class="cambio__lista">
        <li
          v-for="item in datos.items"
          :key="item.id"
          :class="{ 'cambio__fila--off': !item.disponibles }"
        >
          <span
            class="cambio__swatch"
            :style="{ background: item.color.hexadecimal }"
          />
          <span class="cambio__nombre">
            {{ item.producto }}
            <span class="cambio__hint">
              Talla {{ item.talla }} · {{ item.color.nombre }} ·
              {{ item.disponibles ? `${formatearPrecio(item.valor_unitario)} c/u pagado` : 'ya se cambió' }}
            </span>
          </span>
          <q-input
            v-if="item.disponibles"
            v-model.number="vuelven[item.id]"
            type="number"
            :min="0"
            :max="item.disponibles"
            dense
            outlined
            hide-bottom-space
            :aria-label="`Unidades que vuelven de ${item.producto} talla ${item.talla}`"
            class="cambio__cantidad"
          >
            <template #append>
              <span class="cambio__de">/ {{ item.disponibles }}</span>
            </template>
          </q-input>
        </li>
      </ul>
    </section>

    <!-- Venta de "Cliente varios": el saldo necesita dueño. -->
    <section v-if="!datos.cliente">
      <h3 class="cambio__titulo">
        Cliente
      </h3>
      <p class="cambio__hint">
        La venta fue sin cliente: elegí a quién le queda el saldo a favor.
      </p>
      <BuscadorCliente v-model="cliente" />
    </section>

    <!-- ── 2. Qué se lleva ── -->
    <section>
      <h3 class="cambio__titulo">
        2. ¿Qué se lleva? <span class="cambio__hint">opcional: si no elige nada, queda todo a favor</span>
      </h3>
      <BuscadorVariante
        label="Buscar la prenda nueva: SKU o nombre"
        :excluir="nuevos.map((n) => n.variante.id)"
        @elegir="agregar"
      />
      <ul
        v-if="nuevos.length"
        class="cambio__lista"
      >
        <li
          v-for="(n, i) in nuevos"
          :key="n.variante.id"
        >
          <span
            class="cambio__swatch"
            :style="{ background: n.variante.color?.hexadecimal }"
          />
          <span class="cambio__nombre">
            {{ n.variante.producto?.nombre }}
            <span class="cambio__hint">
              Talla {{ n.variante.talla }} · {{ n.variante.color?.nombre }} · {{ formatearPrecio(n.variante.precio) }} c/u
              <template v-if="n.variante.oferta"> ({{ n.variante.oferta }})</template>
            </span>
          </span>
          <q-input
            v-model.number="n.cantidad"
            type="number"
            :min="1"
            :max="n.variante.stock"
            dense
            outlined
            hide-bottom-space
            :aria-label="`Unidades de ${n.variante.producto?.nombre}`"
            class="cambio__cantidad"
          />
          <q-btn
            flat
            dense
            round
            icon="close"
            size="sm"
            :aria-label="`Quitar ${n.variante.producto?.nombre}`"
            @click="nuevos.splice(i, 1)"
          />
        </li>
      </ul>
    </section>

    <!-- ── 3. Cuentas ── -->
    <section class="cambio__cuentas">
      <div>
        <span>Vuelve</span>
        <strong class="text-mono">{{ formatearPrecio(valorDevuelto) }}</strong>
      </div>
      <div v-if="saldoPrevio">
        <span>Saldo que ya tenía</span>
        <strong class="text-mono">{{ formatearPrecio(saldoPrevio) }}</strong>
      </div>
      <div>
        <span>Se lleva</span>
        <strong class="text-mono">{{ formatearPrecio(totalNuevo) }}</strong>
      </div>
      <div class="cambio__resultado">
        <template v-if="diferencia > 0">
          <span>Paga la diferencia</span>
          <strong class="text-mono">{{ formatearPrecio(diferencia) }}</strong>
        </template>
        <template v-else>
          <span>Queda a favor</span>
          <strong class="text-mono cambio__favor">{{ formatearPrecio(-diferencia) }}</strong>
        </template>
      </div>
    </section>

    <!-- La diferencia se cobra en caja, con plata (el saldo ya se aplicó). -->
    <section
      v-if="diferencia > 0"
      class="cambio__pago"
    >
      <q-select
        v-model="metodo"
        :options="METODOS"
        emit-value
        map-options
        dense
        outlined
        label="Método"
        class="cambio__metodo"
      />
      <q-input
        v-if="metodo === 'efectivo'"
        v-model="recibido"
        type="number"
        min="0"
        step="0.01"
        dense
        outlined
        label="Recibido (opcional)"
        :hint="vuelto > 0 ? `Vuelto: ${formatearPrecio(vuelto)}` : ''"
      />
      <q-input
        v-if="CON_OPERACION.includes(metodo)"
        v-model="referencia"
        dense
        outlined
        maxlength="40"
        label="N° de operación"
      />
    </section>

    <q-input
      v-model="observacion"
      dense
      outlined
      maxlength="500"
      label="Motivo (opcional)"
      placeholder="Le quedó chico, otro color…"
    />

    <ul
      v-if="errores.length"
      class="cambio__errores"
      role="alert"
    >
      <li
        v-for="e in errores"
        :key="e"
      >
        {{ e }}
      </li>
    </ul>
  </form>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { CON_OPERACION, METODOS } from '@/modules/Caja/constantes'
import BuscadorVariante from '@/modules/Inventario/BuscadorVariante.vue'
import CambioService from '@/services/CambioService'
import { formatearPrecio } from '@/utils/moneda'
import BuscadorCliente from './BuscadorCliente.vue'

/**
 * Cambio de prenda de una venta entregada. Las cuentas de acá son para
 * mostrar: las reglas (plazo, cantidades, diferencia exacta) las vuelve a
 * validar el servidor con la base bloqueada.
 */
const props = defineProps({
  pedidoId: {
    type: Number,
    required: true
  }
})

const emit = defineEmits(['save'])

const datos = ref(null)
const vuelven = ref({})
const nuevos = ref([])
const cliente = ref(null)
const metodo = ref('efectivo')
const recibido = ref('')
const referencia = ref('')
const observacion = ref('')
const procesando = ref(false)
const errores = ref([])

onMounted(async () => {
  datos.value = await CambioService.preparar(props.pedidoId)
  // Lo más común: vuelve una unidad de una sola prenda.
  const disponibles = datos.value.items.filter((i) => i.disponibles)
  if (disponibles.length === 1) vuelven.value[disponibles[0].id] = 1
})

function agregar (variante) {
  nuevos.value.push({ variante, cantidad: 1 })
}

const cent = (n) => Math.round(n * 100) / 100

const devueltos = computed(() => (datos.value?.items ?? [])
  .map((i) => ({ item: i, cantidad: Math.min(Math.max(0, Number(vuelven.value[i.id]) || 0), i.disponibles) }))
  .filter((d) => d.cantidad > 0))

const valorDevuelto = computed(() => cent(devueltos.value.reduce((s, d) => s + d.cantidad * d.item.valor_unitario, 0)))
const saldoPrevio = computed(() => datos.value?.cliente?.saldo ?? 0)
const totalNuevo = computed(() => cent(nuevos.value.reduce((s, n) => s + (Number(n.cantidad) || 0) * Number(n.variante.precio), 0)))
// > 0: paga la diferencia; ≤ 0: queda a favor.
const diferencia = computed(() => cent(totalNuevo.value - valorDevuelto.value - saldoPrevio.value))
const vuelto = computed(() => (recibido.value === '' ? 0 : cent(Number(recibido.value) - diferencia.value)))

async function submit () {
  errores.value = []
  procesando.value = true
  try {
    const respuesta = await CambioService.registrar(props.pedidoId, {
      cliente_id: datos.value.cliente ? null : cliente.value?.id ?? null,
      observacion: observacion.value || null,
      devueltos: devueltos.value.map((d) => ({ pedido_item_id: d.item.id, cantidad: d.cantidad })),
      nuevos: nuevos.value.map((n) => ({ variante_id: n.variante.id, cantidad: Number(n.cantidad) })),
      pagos: diferencia.value > 0
        ? [{
            metodo: metodo.value,
            monto: diferencia.value,
            recibido: metodo.value === 'efectivo' && recibido.value !== '' ? Number(recibido.value) : null,
            referencia: CON_OPERACION.includes(metodo.value) ? referencia.value : null
          }]
        : []
    })
    emit('save', respuesta)
  } catch (error) {
    const data = error.response?.data
    errores.value = data?.errors ? Object.values(data.errors).flat() : [data?.message ?? 'No se pudo registrar el cambio.']
  } finally {
    procesando.value = false
  }
}

defineExpose({ submit, procesando, puedeEnviar: computed(() => Boolean(datos.value?.cambiable) && devueltos.value.length > 0) })
</script>

<style lang="scss" scoped>
.cambio {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.cambio__cargando {
  display: flex;
  justify-content: center;
  padding: 32px;
}

.cambio__bloqueado {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px;
  border-radius: 10px;
  background: rgba($negative, 0.08);
  font-size: 14px;
  color: var(--app-ink);
}

.cambio__ayuda {
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
  color: var(--app-ink-2);
}

.cambio__titulo {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 8px;
  margin: 0 0 10px;
  font-size: 14px;
  font-weight: 700;
  line-height: 1.3;
  color: var(--app-ink);
}

.cambio__hint {
  font-size: 12px;
  font-weight: 500;
  color: var(--app-ink-2);
}

.cambio__lista {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 10px 0 0;
  padding: 0;
  list-style: none;

  li {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13.5px;
  }
}

.cambio__fila--off {
  opacity: 0.55;
}

.cambio__swatch {
  flex-shrink: 0;
  width: 14px;
  height: 14px;
  border: 1px solid var(--app-border-control);
  border-radius: 4px;
}

.cambio__nombre {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
  font-weight: 600;
  color: var(--app-ink);
}

.cambio__cantidad {
  width: 96px;
}

.cambio__de {
  font-size: 12px;
  color: var(--app-ink-2);
}

.cambio__cuentas {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px;
  border-radius: 12px;
  background: var(--app-page);
  font-size: 13.5px;

  div {
    display: flex;
    justify-content: space-between;
    color: var(--app-ink-2);
  }

  strong {
    color: var(--app-ink);
  }
}

.cambio__resultado {
  margin-top: 4px;
  padding-top: 8px;
  border-top: 1px solid var(--app-border-subtle);
  font-weight: 700;

  span {
    color: var(--app-ink);
  }
}

.cambio__favor {
  color: var(--q-positive) !important;
}

.cambio__pago {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 10px;
}

.cambio__errores {
  margin: 0;
  padding: 10px 14px 10px 28px;
  border-radius: 10px;
  background: rgba($negative, 0.08);
  font-size: 13px;
  color: var(--q-negative);
}
</style>
