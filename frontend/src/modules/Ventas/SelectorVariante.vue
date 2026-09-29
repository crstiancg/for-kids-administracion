<template>
  <div class="selector">
    <!-- ── Foto grande: la del color que se está mirando ── -->
    <figure class="selector__vista">
      <div
        class="selector__foto"
        :style="fotoVista ? { backgroundImage: `url(${fotoVista})` } : fondoSinFoto"
      >
        <q-icon
          v-if="!fotoVista"
          name="checkroom"
          size="44px"
        />
      </div>
      <figcaption class="selector__pie">
        <div class="selector__nombre">
          {{ producto.nombre }}
        </div>
        <div
          v-if="producto.oferta"
          class="selector__oferta"
        >
          {{ producto.oferta.etiqueta }} · {{ producto.oferta.nombre }}
        </div>
        <div
          v-if="colorVista"
          class="selector__colorVista"
        >
          <span
            class="selector__swatch"
            :style="{ background: colorVista.hexadecimal }"
          />
          {{ colorVista.nombre }}
        </div>
      </figcaption>
    </figure>

    <!-- ── Talla × color ── -->
    <div class="selector__grilla">
      <p class="selector__ayuda">
        Tocá una combinación para sumar una unidad. Podés sumar varias antes de cerrar.
      </p>

      <div class="selector__tablaWrap">
        <table class="selector__tabla">
          <thead>
            <tr>
              <th scope="col">
                <span class="sr-only">Talla</span>
              </th>
              <th
                v-for="color in colores"
                :key="color.id"
                scope="col"
              >
                <!-- La foto del color (o su muestra, si no tiene foto). -->
                <button
                  type="button"
                  :class="['selector__colorCab', { 'selector__colorCab--activo': colorVista?.id === color.id }]"
                  :aria-label="`Ver ${color.nombre}`"
                  @mouseenter="verColor(color.id)"
                  @focus="verColor(color.id)"
                  @click="verColor(color.id)"
                >
                  <span
                    class="selector__colorFoto"
                    :style="fotoDeColor(color.id)
                      ? { backgroundImage: `url(${fotoDeColor(color.id)})` }
                      : { background: color.hexadecimal }"
                  />
                  <span class="selector__colorNombre">{{ color.nombre }}</span>
                </button>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="talla in tallas"
              :key="talla.id"
            >
              <th scope="row">
                {{ talla.nombre }}
              </th>
              <td
                v-for="color in colores"
                :key="color.id"
              >
                <template v-if="celda(talla.id, color.id)">
                  <button
                    type="button"
                    :class="['selector__celda', {
                      'selector__celda--agotada': !disponible(celda(talla.id, color.id)),
                      'selector__celda--flash': flash === celda(talla.id, color.id).id
                    }]"
                    :disabled="!disponible(celda(talla.id, color.id))"
                    :aria-label="`Talla ${talla.nombre}, ${color.nombre}: ${celda(talla.id, color.id).stock} en stock`"
                    @mouseenter="verColor(color.id)"
                    @focus="verColor(color.id)"
                    @click="agregar(celda(talla.id, color.id))"
                  >
                    <span class="selector__stock">
                      {{ celda(talla.id, color.id).stock ? celda(talla.id, color.id).stock : 'Agotado' }}
                    </span>
                    <span
                      v-if="precioDistinto(celda(talla.id, color.id))"
                      :class="['selector__precio', 'text-mono', { 'selector__precio--oferta': enOferta(celda(talla.id, color.id)) }]"
                    >{{ formatearPrecio(celda(talla.id, color.id).precio) }}</span>
                    <span
                      v-if="pos.cantidadDeVariante(celda(talla.id, color.id).id)"
                      class="selector__enCarrito"
                    >×{{ pos.cantidadDeVariante(celda(talla.id, color.id).id) }}</span>
                  </button>
                </template>
                <span
                  v-else
                  class="selector__noExiste"
                >—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { lineaDesdeCatalogo, usePosStore } from '@/stores/pos-store'
import { formatearPrecio } from '@/utils/moneda'
import { beepError, beepOk } from '@/utils/sonido'

/**
 * Grilla talla × color de un producto con el stock de cada combinación y la
 * foto del color que se está mirando (en ropa el color se elige viendo la
 * prenda). Es el equivalente, para ropa, del "¿unidad o blíster?" de
 * sistema-botica.
 */
const props = defineProps({
  // CatalogoProductoResource.
  producto: {
    type: Object,
    required: true
  }
})

const pos = usePosStore()

// Tallas en su orden (así vienen las variantes) y colores sin repetir.
const tallas = computed(() => {
  const vistas = new Map()
  props.producto.variantes.forEach((v) => { if (v.talla) vistas.set(v.talla.id, v.talla) })
  return [...vistas.values()]
})
const colores = computed(() => {
  const vistos = new Map()
  props.producto.variantes.forEach((v) => { if (v.color) vistos.set(v.color.id, v.color) })
  return [...vistos.values()]
})

const porCelda = computed(() => new Map(props.producto.variantes.map((v) => [`${v.talla?.id}-${v.color?.id}`, v])))

function celda (tallaId, colorId) {
  return porCelda.value.get(`${tallaId}-${colorId}`)
}

// ── Fotos ──
// El catálogo manda, por variante, su foto o (si no tiene) la del producto:
// es foto del color sólo si es distinta de la del producto.
function fotoDeColor (colorId) {
  const conFoto = props.producto.variantes.find((v) =>
    v.color?.id === colorId && v.miniatura_url && v.miniatura_url !== props.producto.miniatura_url)
  return conFoto?.miniatura_url ?? null
}

// Arranca en el primer color con foto propia; si ninguno tiene, el primero.
const colorVistaId = ref(colores.value.find((c) => fotoDeColor(c.id))?.id ?? colores.value[0]?.id ?? null)
const colorVista = computed(() => colores.value.find((c) => c.id === colorVistaId.value) ?? null)
const fotoVista = computed(() => (colorVista.value && fotoDeColor(colorVista.value.id)) || props.producto.miniatura_url)

function verColor (colorId) {
  colorVistaId.value = colorId
}

// Sin ninguna foto: el mismo degradé que la tarjeta del catálogo.
const fondoSinFoto = computed(() => {
  const tono = ((props.producto.nombre || 'A').charCodeAt(0) * 23) % 360
  return { background: `linear-gradient(180deg, hsl(${tono} 60% 96%), hsl(${tono} 45% 90%))` }
})

// ── Agregar ──
// Queda stock después de lo que ya está en el carrito.
function disponible (variante) {
  return variante.stock - pos.cantidadDeVariante(variante.id) > 0
}

// Se muestra el precio de la celda si difiere del base o si está en oferta.
function precioDistinto (variante) {
  return Number(variante.precio) !== Number(props.producto.precio) || enOferta(variante)
}

function enOferta (variante) {
  return Number(variante.precio) < Number(variante.precio_lista)
}

// Destello en la celda recién sumada.
const flash = ref(null)
let flashTimer

function agregar (variante) {
  const resultado = pos.agregar(lineaDesdeCatalogo(props.producto, variante))
  if (resultado === 'ok') {
    beepOk()
    flash.value = variante.id
    clearTimeout(flashTimer)
    flashTimer = setTimeout(() => { flash.value = null }, 350)
  } else {
    beepError()
  }
}
</script>

<style lang="scss" scoped>
.selector {
  display: grid;
  grid-template-columns: 240px minmax(0, 1fr);
  align-items: start;
  gap: 20px;

  @media (max-width: 699px) {
    grid-template-columns: 1fr;
  }
}

// ── Vista grande ──
.selector__vista {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0;

  @media (max-width: 699px) {
    flex-direction: row;
    align-items: center;
  }
}

.selector__foto {
  display: flex;
  align-items: center;
  justify-content: center;
  aspect-ratio: 1 / 1;
  border: 1px solid var(--app-border-subtle);
  border-radius: 14px;
  background-position: center;
  background-size: cover;
  color: rgba(0, 0, 0, 0.25);
  transition: background-image 0.2s ease;

  @media (max-width: 699px) {
    flex: 0 0 96px;
  }
}

.selector__nombre {
  font-size: 16px;
  font-weight: 700;
  line-height: 1.3;
  color: var(--app-ink);
}

.selector__colorVista {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 2px;
  font-size: 13px;
  color: var(--app-ink-2);
}

.selector__swatch {
  display: inline-block;
  width: 12px;
  height: 12px;
  border: 1px solid var(--app-border-control);
  border-radius: 50%;
}

// ── Grilla ──
.selector__grilla {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 0;
}

.selector__ayuda {
  margin: 0;
  font-size: 12px;
  color: var(--app-ink-2);
}

.selector__tablaWrap {
  overflow-x: auto;
}

.selector__tabla {
  border-collapse: separate;
  border-spacing: 6px;
  margin: -6px;

  th {
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    color: var(--app-ink-2);
  }

  thead th {
    padding-bottom: 2px;
    text-align: center;
    vertical-align: bottom;
  }

  tbody th {
    padding-right: 6px;
    font-size: 14px;
    text-align: right;
    color: var(--app-ink);
  }
}

// Cabecera de color: la foto del color como botón (mirar sin agregar).
.selector__colorCab {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  width: 84px;
  padding: 4px;
  border: 1px solid transparent;
  border-radius: 10px;
  background: none;
  cursor: pointer;

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 1px;
  }

  &--activo {
    border-color: $primary;
    background: rgba($primary, 0.06);
  }
}

.selector__colorFoto {
  width: 52px;
  height: 52px;
  border: 1px solid var(--app-border-control);
  border-radius: 10px;
  background-position: center;
  background-size: cover;
}

.selector__colorNombre {
  max-width: 76px;
  overflow: hidden;
  font-size: 11.5px;
  font-weight: 600;
  text-overflow: ellipsis;
  color: var(--app-ink);
}

.selector__celda {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 84px;
  height: 52px;
  padding: 0;
  border: 1px solid var(--app-border-control);
  border-radius: 10px;
  background: var(--app-surface);
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;

  &:hover:not(:disabled) {
    border-color: $primary;
    background: rgba($primary, 0.06);
  }

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 2px;
  }

  &--agotada {
    cursor: not-allowed;
    opacity: 0.4;
  }

  &--flash {
    border-color: $primary;
    background: rgba($primary, 0.18);
  }
}

.selector__stock {
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);
}

.selector__precio {
  font-size: 10.5px;
  color: var(--app-ink-2);

  &--oferta {
    font-weight: 700;
    color: #DC2626;
  }
}

.selector__oferta {
  display: inline-block;
  margin-top: 4px;
  padding: 2px 8px;
  border-radius: 6px;
  background: #DC2626;
  font-size: 12px;
  font-weight: 700;
  color: #FFFFFF;
}

.selector__enCarrito {
  position: absolute;
  top: -7px;
  right: -7px;
  min-width: 22px;
  padding: 1px 6px;
  border-radius: 999px;
  background: $primary;
  font-size: 11px;
  font-weight: 700;
  color: #FFFFFF;
}

.selector__noExiste {
  display: block;
  width: 84px;
  text-align: center;
  color: var(--app-ink-2);
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}
</style>
