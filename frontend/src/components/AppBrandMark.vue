<template>
  <img
    :src="logo"
    alt=""
    :class="['brand-mark', { 'brand-mark--glow': glow }]"
    :style="{ '--mark-size': `${size}px` }"
    v-bind="a11y"
    draggable="false"
  >
</template>

<script setup>
import { computed } from 'vue'
import logo from '@/assets/logo-nc.webp'

/**
 * El logo de la marca, en un solo lugar: drawer, acceso y cualquier lugar
 * futuro. El archivo es el mismo del que salen los favicons (public/icons).
 */
const props = defineProps({
  // ALTO en px (el logo es apaisado: el ancho sale solo). El sistema lo usa
  // a 40 (drawer) y 56 (acceso).
  size: {
    type: Number,
    default: 40
  },

  // Resplandor de marca. Va sobre superficies oscuras o fotografía, donde el
  // rojo se pega al fondo. Sobre superficie clara sobra.
  glow: {
    type: Boolean,
    default: false
  },

  // Nombre accesible. Vacío = decorativo, que es el caso NORMAL: en el drawer
  // y en el acceso la marca va pegada al nombre escrito en texto, y anunciar
  // las dos cosas hace que el lector de pantalla diga la marca dos veces.
  // Sólo se pasa cuando el logo va solo, sin el nombre al lado.
  label: {
    type: String,
    default: ''
  }
})

const a11y = computed(() => (
  props.label
    ? { role: 'img', alt: props.label, 'aria-label': props.label }
    : { 'aria-hidden': 'true' }
))
</script>

<style lang="scss" scoped>
.brand-mark {
  display: block;
  flex-shrink: 0;
  height: var(--mark-size);
  width: auto;
  user-select: none;
}

// drop-shadow y no box-shadow: el logo tiene transparencia, así el brillo
// sigue la silueta (los ositos y la cursiva) y no un rectángulo.
.brand-mark--glow {
  filter: drop-shadow(0 calc(var(--mark-size) * 0.08) calc(var(--mark-size) * 0.25) rgba($primary, 0.45));
}
</style>
