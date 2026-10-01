<template>
  <!-- Medidas en mm reales: en pantalla es la vista previa y al imprimir sale
       exactamente de ese tamaño. Todo en negro: térmicas y láser lo
       imprimen igual de bien. -->
  <div
    class="etiqueta"
    :style="estilo"
  >
    <div class="etiqueta__texto">
      <span class="etiqueta__nombre">{{ variante.producto?.nombre }}</span>
      <span class="etiqueta__talla">T. {{ variante.talla }}</span>
    </div>
    <div class="etiqueta__codigo">
      <CodigoBarras
        :codigo="variante.codigo_barras ?? ''"
        :style="{ width: `${anchoCodigo(formato)}mm` }"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import CodigoBarras from './CodigoBarras.vue'
import { anchoCodigo } from './formatos'

const props = defineProps({
  // { codigo_barras, producto: { nombre }, talla } (EtiquetaResource)
  variante: {
    type: Object,
    required: true
  },
  formato: {
    type: Object,
    required: true
  }
})

const estilo = computed(() => ({
  width: `${props.formato.ancho}mm`,
  height: `${props.formato.alto}mm`,
  padding: `${props.formato.padding}mm`,
  fontSize: `${props.formato.fuente}mm`
}))
</script>

<style lang="scss" scoped>
.etiqueta {
  display: flex;
  flex-direction: column;
  gap: 0.8mm;
  overflow: hidden;
  box-sizing: border-box;
  background: #FFFFFF;
  color: #000000;
  font-family: Arial, Helvetica, sans-serif;
  line-height: 1.25;
}

.etiqueta__texto {
  display: flex;
  align-items: baseline;
  gap: 1.5mm;
  min-width: 0;
}

.etiqueta__nombre {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  font-weight: 600;
}

// La talla es lo que más se busca en el colgador: que no se corte nunca.
.etiqueta__talla {
  flex-shrink: 0;
  font-weight: 800;
}

.etiqueta__codigo {
  display: flex;
  flex: 1;
  align-items: center;
  justify-content: center;
  min-height: 0;
}
</style>
