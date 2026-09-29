<template>
  <div class="ticket">
    <header class="ticket__centro">
      <div class="ticket__tienda">
        {{ TIENDA.nombre }}
      </div>
      <div v-if="TIENDA.ruc">
        RUC {{ TIENDA.ruc }}
      </div>
      <div v-if="TIENDA.direccion">
        {{ TIENDA.direccion }}
      </div>
      <div v-if="TIENDA.telefono">
        Tel. {{ TIENDA.telefono }}
      </div>
    </header>

    <div class="ticket__separador" />

    <!-- Hasta emitir boletas electrónicas, esto NO es un comprobante de pago. -->
    <div class="ticket__centro ticket__tipo">
      TICKET DE VENTA {{ pedido.codigo }}
    </div>
    <div class="ticket__centro">
      No es comprobante de pago
    </div>

    <div class="ticket__separador" />

    <div>{{ formatearFecha(pedido.confirmado_at ?? pedido.fecha) }}</div>
    <div v-if="pedido.usuario">
      Atendió: {{ pedido.usuario.name }}
    </div>
    <div v-if="pedido.cliente">
      Cliente: {{ pedido.cliente.nombre }}
      <template v-if="pedido.cliente.numero_documento">
        ({{ pedido.cliente.tipo_documento }} {{ pedido.cliente.numero_documento }})
      </template>
    </div>

    <div class="ticket__separador" />

    <div
      v-for="item in pedido.items"
      :key="item.id"
      class="ticket__item"
    >
      <div>
        {{ item.variante.producto?.nombre }} T.{{ item.variante.talla }} {{ item.variante.color?.nombre }}
      </div>
      <div class="ticket__fila">
        <span>{{ item.cantidad }} x {{ formatearPrecio(item.precio_unitario) }}</span>
        <span>{{ formatearPrecio(item.subtotal) }}</span>
      </div>
    </div>

    <div class="ticket__separador" />

    <div
      v-if="Number(pedido.descuento)"
      class="ticket__fila"
    >
      <span>Subtotal</span>
      <span>{{ formatearPrecio(pedido.subtotal) }}</span>
    </div>
    <div
      v-if="Number(pedido.descuento)"
      class="ticket__fila"
    >
      <span>Descuento</span>
      <span>-{{ formatearPrecio(pedido.descuento) }}</span>
    </div>
    <div class="ticket__fila ticket__total">
      <span>TOTAL</span>
      <span>{{ formatearPrecio(pedido.total) }}</span>
    </div>

    <div class="ticket__separador" />

    <template
      v-for="pago in pagosCobro"
      :key="pago.id"
    >
      <div class="ticket__fila">
        <span>{{ pago.metodo_label }}<template v-if="pago.referencia"> ({{ pago.referencia }})</template></span>
        <span>{{ formatearPrecio(pago.recibido ?? pago.monto) }}</span>
      </div>
      <div
        v-if="Number(pago.vuelto) > 0"
        class="ticket__fila"
      >
        <span>Vuelto</span>
        <span>{{ formatearPrecio(pago.vuelto) }}</span>
      </div>
    </template>

    <div class="ticket__separador" />

    <div class="ticket__centro">
      {{ TIENDA.pie }}
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { TIENDA } from '@/config/tienda'
import { formatearPrecio } from '@/utils/moneda'

/**
 * El ticket de 80 mm, tal cual sale impreso (también sirve de vista previa).
 * Recibe un PedidoResource con ítems y pagos.
 */
const props = defineProps({
  pedido: {
    type: Object,
    required: true
  }
})

const formatoFecha = new Intl.DateTimeFormat('es-PE', { dateStyle: 'short', timeStyle: 'short' })
function formatearFecha (iso) {
  return iso ? formatoFecha.format(new Date(iso)) : ''
}

// Las devoluciones no van en el ticket de la venta.
const pagosCobro = computed(() => (props.pedido.pagos ?? []).filter((p) => !p.es_devolucion))
</script>

<style lang="scss" scoped>
// 80 mm de papel ≈ 72 mm imprimibles. Negro puro y monoespaciada: las
// ticketeras térmicas no imprimen grises y así las columnas se alinean.
.ticket {
  width: 72mm;
  margin: 0 auto;
  padding: 4mm 0;
  background: #FFFFFF;
  color: #000000;
  font-family: $font-mono;
  font-size: 11.5px;
  line-height: 1.35;
}

.ticket__centro {
  text-align: center;
}

.ticket__tienda {
  font-size: 15px;
  font-weight: 700;
}

.ticket__tipo {
  font-weight: 700;
}

.ticket__separador {
  margin: 6px 0;
  border-top: 1px dashed #000000;
}

.ticket__item + .ticket__item {
  margin-top: 4px;
}

.ticket__fila {
  display: flex;
  justify-content: space-between;
  gap: 8px;
}

.ticket__total {
  font-size: 14px;
  font-weight: 700;
}
</style>
