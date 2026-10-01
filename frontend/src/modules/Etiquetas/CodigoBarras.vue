<template>
  <!-- Vectorial: se imprime nítido a cualquier tamaño. El ancho lo pone quien
       lo usa; el alto sale de la proporción del viewBox. -->
  <svg
    v-if="barras"
    class="codigo-barras"
    :viewBox="`0 0 ${ANCHO} ${ALTO}`"
    shape-rendering="crispEdges"
    role="img"
    :aria-label="`Código de barras ${codigo}`"
  >
    <rect
      :width="ANCHO"
      :height="ALTO"
      fill="#FFFFFF"
    />
    <rect
      v-for="barra in barras"
      :key="barra.x"
      :x="MARGEN_IZQ + barra.x"
      y="0"
      :width="barra.ancho"
      :height="barra.guarda ? ALTO_GUARDA : ALTO_BARRA"
      fill="#000000"
    />
    <g
      class="codigo-barras__digitos"
      fill="#000000"
      :font-size="TEXTO"
    >
      <text
        x="1"
        :y="ALTO - 1"
      >{{ codigo[0] }}</text>
      <text
        :x="MARGEN_IZQ + 4"
        :y="ALTO - 1"
        textLength="38"
        lengthAdjust="spacing"
      >{{ codigo.slice(1, 7) }}</text>
      <text
        :x="MARGEN_IZQ + 51"
        :y="ALTO - 1"
        textLength="38"
        lengthAdjust="spacing"
      >{{ codigo.slice(7) }}</text>
    </g>
  </svg>
  <span
    v-else
    class="codigo-barras__invalido"
  >Código inválido: {{ codigo }}</span>
</template>

<script setup>
import { computed } from 'vue'
import { barrasEan13, esEan13 } from '@/utils/ean13'

const props = defineProps({
  codigo: {
    type: String,
    required: true
  }
})

// En módulos (el ancho de la barra más fina). Los márgenes en blanco a los
// costados son parte del estándar: sin ellos el lector no encuentra el inicio.
const MARGEN_IZQ = 11
const MARGEN_DER = 7
const ANCHO = MARGEN_IZQ + 95 + MARGEN_DER
const ALTO_BARRA = 46
const ALTO_GUARDA = 51
const TEXTO = 9
const ALTO = ALTO_GUARDA + TEXTO

const barras = computed(() => (esEan13(props.codigo) ? barrasEan13(props.codigo) : null))
</script>

<style lang="scss" scoped>
.codigo-barras {
  display: block;
  width: 100%;
  height: auto;
}

.codigo-barras__digitos {
  font-family: 'Courier New', monospace;
}

.codigo-barras__invalido {
  font-size: 9px;
  color: #000000;
}
</style>
