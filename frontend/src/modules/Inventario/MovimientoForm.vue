<template>
  <form
    class="movimiento-form"
    novalidate
    @submit.prevent="submit"
  >
    <p class="movimiento-form__ayuda">
      {{ config.ayuda }}
    </p>

    <!-- ── Encabezado del documento ── -->
    <div class="movimiento-form__row">
      <div
        v-if="tipo === 'salida'"
        class="movimiento-form__field movimiento-form__grow"
      >
        <label
          :id="`${uid}-motivo`"
          class="movimiento-form__label"
        >Motivo</label>
        <q-select
          v-model="form.movimiento.motivo"
          :options="MOTIVOS_SALIDA"
          :aria-labelledby="`${uid}-motivo`"
          :error="Boolean(form.errors[`${PATH}.motivo`])"
          :error-message="form.errors[`${PATH}.motivo`]"
          placeholder="Elegí el motivo"
          dense
          outlined
          hide-bottom-space
          no-error-icon
          emit-value
          map-options
          class="movimiento-form__control"
          @update:model-value="form.validate(`${PATH}.motivo`)"
        />
      </div>

      <AppTextField
        v-model="form.movimiento.referencia"
        :label="tipo === 'entrada' ? 'Referencia (factura o guía)' : 'Referencia (opcional)'"
        icon="receipt"
        :placeholder="tipo === 'entrada' ? 'F001-2345' : ''"
        maxlength="60"
        class="movimiento-form__grow"
        :error="form.errors[`${PATH}.referencia`]"
        @change="form.validate(`${PATH}.referencia`)"
      />
    </div>

    <!-- ── Líneas ── -->
    <section class="movimiento-form__lineas">
      <BuscadorVariante
        :excluir="form.movimiento.lineas.map((l) => l.variante_id)"
        @elegir="agregar"
      />

      <p
        v-if="form.errors[`${PATH}.lineas`]"
        class="movimiento-form__error"
        role="alert"
      >
        {{ form.errors[`${PATH}.lineas`] }}
      </p>

      <div
        v-if="form.movimiento.lineas.length"
        class="movimiento-form__tablaWrap"
      >
        <table class="movimiento-form__tabla">
          <thead>
            <tr>
              <th scope="col">
                Variante
              </th>
              <th
                scope="col"
                class="text-right"
              >
                Stock
              </th>
              <th scope="col">
                {{ tipo === 'ajuste' ? 'Contado' : 'Cantidad' }}
              </th>
              <th
                v-if="tipo === 'entrada'"
                scope="col"
              >
                Costo unit.
              </th>
              <th
                scope="col"
                class="text-right"
              >
                Queda
              </th>
              <th scope="col">
                <span class="sr-only">Quitar</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(linea, i) in form.movimiento.lineas"
              :key="linea.variante_id"
            >
              <td>
                <div class="movimiento-form__variante">
                  <span
                    class="movimiento-form__swatch"
                    :style="{ background: linea.variante.color?.hexadecimal }"
                  />
                  <div>
                    <div class="movimiento-form__producto">
                      {{ linea.variante.producto?.nombre }}
                    </div>
                    <div class="movimiento-form__detalle">
                      Talla {{ linea.variante.talla }} · {{ linea.variante.color?.nombre }} ·
                      <span class="text-mono">{{ linea.variante.sku }}</span>
                    </div>
                  </div>
                </div>
                <p
                  v-if="errorDe(i, 'variante_id')"
                  class="movimiento-form__error"
                >
                  {{ errorDe(i, 'variante_id') }}
                </p>
              </td>

              <td class="text-right text-mono">
                {{ linea.variante.stock }}
              </td>

              <td class="movimiento-form__numero">
                <q-input
                  v-if="tipo === 'ajuste'"
                  v-model="linea.stock_real"
                  :aria-label="`Stock contado de ${linea.variante.sku}`"
                  type="number"
                  min="0"
                  step="1"
                  dense
                  outlined
                  hide-bottom-space
                  no-error-icon
                  :error="Boolean(errorDe(i, 'stock_real'))"
                  :error-message="errorDe(i, 'stock_real')"
                  class="movimiento-form__control"
                  @change="form.validate(`${PATH}.lineas.${i}.stock_real`)"
                />
                <q-input
                  v-else
                  v-model="linea.cantidad"
                  :aria-label="`Cantidad de ${linea.variante.sku}`"
                  type="number"
                  min="1"
                  step="1"
                  dense
                  outlined
                  hide-bottom-space
                  no-error-icon
                  :error="Boolean(errorDe(i, 'cantidad'))"
                  :error-message="errorDe(i, 'cantidad')"
                  class="movimiento-form__control"
                  @change="form.validate(`${PATH}.lineas.${i}.cantidad`)"
                />
              </td>

              <td
                v-if="tipo === 'entrada'"
                class="movimiento-form__numero"
              >
                <q-input
                  v-model="linea.costo_unitario"
                  :aria-label="`Costo unitario de ${linea.variante.sku}`"
                  type="number"
                  min="0"
                  step="0.01"
                  prefix="S/"
                  dense
                  outlined
                  hide-bottom-space
                  no-error-icon
                  :error="Boolean(errorDe(i, 'costo_unitario'))"
                  :error-message="errorDe(i, 'costo_unitario')"
                  class="movimiento-form__control"
                  @change="form.validate(`${PATH}.lineas.${i}.costo_unitario`)"
                />
              </td>

              <!-- Vista previa: cómo queda el stock si se guarda. -->
              <td
                :class="['text-right', 'text-mono', 'movimiento-form__queda', claseDiferencia(linea)]"
              >
                {{ stockResultante(linea) ?? '—' }}
              </td>

              <td class="text-right">
                <q-btn
                  flat
                  dense
                  round
                  icon="close"
                  size="sm"
                  color="grey-7"
                  :aria-label="`Quitar ${linea.variante.sku}`"
                  @click="quitar(i)"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p
        v-else
        class="movimiento-form__vacio"
      >
        Buscá las variantes arriba para agregarlas al documento.
      </p>

      <div
        v-if="tipo === 'entrada' && form.movimiento.lineas.length"
        class="movimiento-form__total"
      >
        <span>{{ unidades }} {{ unidades === 1 ? 'unidad' : 'unidades' }}</span>
        <strong class="text-mono">{{ formatearPrecio(totalCompra) || formatearPrecio(0) }}</strong>
      </div>
    </section>

    <AppTextField
      v-model="form.movimiento.observacion"
      label="Observación (opcional)"
      type="textarea"
      autogrow
      maxlength="500"
      :error="form.errors[`${PATH}.observacion`]"
      @change="form.validate(`${PATH}.observacion`)"
    />

    <button
      type="submit"
      hidden
    />
  </form>
</template>

<script setup>
import { computed, onMounted, useId } from 'vue'
import { useForm } from 'laravel-precognition-vue'
import AppTextField from '@/components/AppTextField.vue'
import InventarioService from '@/services/InventarioService'
import { formatearPrecio } from '@/utils/moneda'
import BuscadorVariante from './BuscadorVariante.vue'
import formMovimiento, { nuevaLinea } from './FormMovimiento'
import { MOTIVOS_SALIDA, TIPOS } from './constantes'

const PATH = 'movimiento'

const props = defineProps({
  tipo: {
    type: String,
    required: true,
    validator: (valor) => valor in TIPOS
  },
  // Desde el detalle de un producto: arranca con todas sus variantes.
  productoId: {
    type: [Number, String],
    default: null
  }
})

const emit = defineEmits(['save'])

const uid = `movimiento-${useId()}`
const config = computed(() => TIPOS[props.tipo])

const form = useForm('post', `api/inventario/${TIPOS[props.tipo].endpoint}`, () => formMovimiento(props.tipo))

function errorDe (i, campo) {
  return form.errors[`${PATH}.lineas.${i}.${campo}`]
}

function agregar (variante) {
  form.movimiento.lineas.push(nuevaLinea(props.tipo, variante))
}

onMounted(async () => {
  if (props.productoId === null) return
  const { data } = await InventarioService.variantes({ params: { producto_id: props.productoId, rowsPerPage: 0 } })
  data.forEach(agregar)
})

function quitar (i) {
  form.movimiento.lineas.splice(i, 1)
}

// null mientras el número no sea válido.
function entero (valor) {
  const numero = Number(valor)
  return valor !== '' && Number.isInteger(numero) ? numero : null
}

function stockResultante (linea) {
  const stock = linea.variante.stock

  if (props.tipo === 'ajuste') return entero(linea.stock_real)

  const cantidad = entero(linea.cantidad)
  if (cantidad === null) return null
  return props.tipo === 'entrada' ? stock + cantidad : stock - cantidad
}

// Rojo si una salida deja el stock negativo; resaltado si el ajuste cambia algo.
function claseDiferencia (linea) {
  const queda = stockResultante(linea)
  if (queda === null) return ''
  if (queda < 0) return 'movimiento-form__queda--negativo'
  return queda !== linea.variante.stock ? 'movimiento-form__queda--cambia' : ''
}

const unidades = computed(() => form.movimiento.lineas.reduce((suma, l) => suma + (entero(l.cantidad) ?? 0), 0))

const totalCompra = computed(() => form.movimiento.lineas.reduce((suma, l) => {
  const cantidad = entero(l.cantidad) ?? 0
  const costo = Number(l.costo_unitario)
  return suma + (Number.isFinite(costo) ? cantidad * costo : 0)
}, 0))

async function submit () {
  // Precargado con todo el producto, sólo se completan las variantes que se
  // movieron: las demás no son un error, se quedan afuera. (El ajuste no lo
  // necesita: lo que coincide con el sistema ya no genera movimiento.)
  if (props.productoId !== null && props.tipo !== 'ajuste') {
    form.movimiento.lineas = form.movimiento.lineas.filter((l) => String(l.cantidad).trim() !== '')
  }

  try {
    const respuesta = await form.submit()
    form.reset()
    emit('save', respuesta?.data?.data ?? [])
  } catch {
    // 422: los errores quedan en form.errors y se ven en cada campo.
  }
}

defineExpose({ form, submit })
</script>

<style lang="scss" scoped>
.movimiento-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.movimiento-form__ayuda {
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
  color: var(--app-ink-2);
}

.movimiento-form__row {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
}

.movimiento-form__grow {
  flex: 1 1 220px;
  min-width: 0;
}

.movimiento-form__field {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.movimiento-form__label {
  font-size: 12.5px;
  font-weight: 600;
  letter-spacing: -0.1px;
  color: var(--app-ink);
}

// Mismo radio, borde y foco que AppTextField.
.movimiento-form__control {
  :deep(.q-field__control) {
    border-radius: 10px;
    background: var(--app-surface);
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
  }

  :deep(.q-field__control):before {
    border-color: var(--app-border-control);
  }

  :deep(.q-field__control):hover:before {
    border-color: var(--app-border-control-hover);
  }

  &.q-field--focused :deep(.q-field__control) {
    box-shadow: 0 0 0 3px rgba($primary, 0.12);
  }
}

.movimiento-form__lineas {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-top: 16px;
  border-top: 1px solid var(--app-border-subtle);
}

.movimiento-form__tablaWrap {
  overflow-x: auto;
}

.movimiento-form__tabla {
  width: 100%;
  min-width: 600px;
  border-collapse: collapse;

  th {
    padding: 0 6px 6px;
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    color: var(--app-ink-2);
    white-space: nowrap;
  }

  td {
    padding: 6px;
    vertical-align: top;
    border-top: 1px solid var(--app-border-subtle);
    font-size: 13px;
    color: var(--app-ink);
  }

  .text-right {
    text-align: right;
  }
}

.movimiento-form__variante {
  display: flex;
  align-items: flex-start;
  gap: 8px;
}

.movimiento-form__swatch {
  flex-shrink: 0;
  width: 16px;
  height: 16px;
  margin-top: 2px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 4px;
}

.movimiento-form__producto {
  font-weight: 600;
}

.movimiento-form__detalle {
  font-size: 12px;
  color: var(--app-ink-2);
}

.movimiento-form__numero {
  width: 130px;
}

.movimiento-form__queda {
  padding-top: 14px !important;
  font-weight: 600;

  &--cambia {
    color: $primary;
  }

  &--negativo {
    color: var(--q-negative);
  }
}

.movimiento-form__total {
  display: flex;
  justify-content: flex-end;
  align-items: baseline;
  gap: 16px;
  font-size: 13px;
  color: var(--app-ink-2);

  strong {
    font-size: 16px;
    color: var(--app-ink);
  }
}

.movimiento-form__vacio {
  margin: 0;
  padding: 16px;
  border: 1px dashed var(--app-border-control);
  border-radius: 10px;
  font-size: 12px;
  text-align: center;
  color: var(--app-ink-2);
}

.movimiento-form__error {
  margin: 4px 0 0;
  font-size: 12px;
  color: var(--q-negative);
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
