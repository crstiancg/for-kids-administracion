<template>
  <form
    class="producto-form"
    novalidate
    @submit.prevent="submit"
  >
    <!-- ── Datos generales ── -->
    <div class="producto-form__row">
      <AppTextField
        v-model="form.producto.nombre"
        label="Nombre"
        icon="checkroom"
        placeholder="Polo básico"
        maxlength="120"
        class="producto-form__grow"
        :error="form.errors[`${PATH}.nombre`]"
        autofocus
        @change="form.validate(`${PATH}.nombre`)"
      />

      <div class="producto-form__field producto-form__grow">
        <label
          :id="`${uid}-categoria`"
          class="producto-form__label"
        >Categoría</label>
        <q-select
          v-model="form.producto.categoria_id"
          :options="categoriasFiltradas"
          :aria-labelledby="`${uid}-categoria`"
          :loading="cargando"
          :error="Boolean(form.errors[`${PATH}.categoria_id`])"
          :error-message="form.errors[`${PATH}.categoria_id`]"
          placeholder="Elegí una categoría"
          dense
          outlined
          hide-bottom-space
          no-error-icon
          emit-value
          map-options
          use-input
          fill-input
          hide-selected
          input-debounce="0"
          class="producto-form__control"
          @filter="filtrarCategorias"
          @update:model-value="form.validate(`${PATH}.categoria_id`)"
        >
          <template #prepend>
            <q-icon
              name="category"
              class="producto-form__icon"
            />
          </template>
          <template #no-option>
            <q-item>
              <q-item-section class="text-grey">
                No hay categorías que coincidan.
              </q-item-section>
            </q-item>
          </template>
        </q-select>
      </div>
    </div>

    <div class="producto-form__row producto-form__row--end">
      <AppTextField
        v-model="form.producto.precio"
        label="Precio base"
        icon="sell"
        type="number"
        min="0"
        step="0.01"
        placeholder="0.00"
        class="producto-form__precio"
        :error="form.errors[`${PATH}.precio`]"
        @change="form.validate(`${PATH}.precio`)"
      />

      <q-toggle
        v-model="form.producto.activo"
        label="Activo (se puede vender)"
        color="primary"
        class="producto-form__toggle"
      />
    </div>

    <AppTextField
      v-model="form.producto.descripcion"
      label="Descripción (opcional)"
      type="textarea"
      autogrow
      maxlength="1000"
      :error="form.errors[`${PATH}.descripcion`]"
      @change="form.validate(`${PATH}.descripcion`)"
    />

    <div class="producto-form__field">
      <span class="producto-form__label">Fotos del producto</span>
      <AppFotos
        v-model="form.producto.archivos"
        label="Fotos del producto"
        :errores="erroresDe(`${PATH}.archivos`)"
      />
      <p class="producto-form__hint">
        Fotos generales (la primera es la portada). Las de cada color van en su variante.
      </p>
    </div>

    <!-- ── Variantes ── -->
    <section class="producto-form__seccion">
      <header>
        <h3 class="producto-form__title">
          Variantes
          <span class="producto-form__count">{{ form.producto.variantes.length }}</span>
        </h3>
        <p class="producto-form__hint">
          Cada combinación de talla y color tiene su SKU, sus fotos y su stock. El stock se carga desde Inventario.
        </p>
      </header>

      <!-- Generador: elegir varias tallas y colores y crear todas las combinaciones. -->
      <div class="producto-form__generador">
        <q-select
          v-model="tallasElegidas"
          :options="opcionesTallas"
          label="Tallas"
          multiple
          use-chips
          dense
          outlined
          emit-value
          map-options
          class="producto-form__control producto-form__grow"
        />
        <q-select
          v-model="coloresElegidos"
          :options="opcionesColores"
          label="Colores"
          multiple
          use-chips
          dense
          outlined
          emit-value
          map-options
          class="producto-form__control producto-form__grow"
        >
          <template #option="scope">
            <q-item v-bind="scope.itemProps">
              <q-item-section side>
                <span
                  class="producto-form__swatch"
                  :style="{ background: scope.opt.hexadecimal }"
                />
              </q-item-section>
              <q-item-section>{{ scope.opt.label }}</q-item-section>
            </q-item>
          </template>
        </q-select>
        <AppButton
          label="Agregar combinaciones"
          icon="add"
          :disable="!tallasElegidas.length || !coloresElegidos.length"
          @click="agregarCombinaciones"
        />
      </div>

      <p
        v-if="form.errors[`${PATH}.variantes`]"
        class="producto-form__error"
        role="alert"
      >
        {{ form.errors[`${PATH}.variantes`] }}
      </p>

      <div
        v-if="form.producto.variantes.length"
        class="producto-form__tablaWrap"
      >
        <div class="producto-form__tabla">
          <div class="producto-form__fila producto-form__fila--head">
            <span>Talla</span>
            <span>Color</span>
            <span>SKU</span>
            <span>Precio</span>
            <span>Fotos</span>
            <span class="text-right">Stock</span>
            <span />
          </div>

          <template
            v-for="(variante, i) in form.producto.variantes"
            :key="variante.uid"
          >
            <div class="producto-form__fila">
              <q-select
                v-model="variante.talla_id"
                :options="opcionesTallas"
                :aria-label="`Talla de la variante ${i + 1}`"
                :error="Boolean(errorDe(i, 'talla_id'))"
                :error-message="errorDe(i, 'talla_id')"
                dense
                outlined
                hide-bottom-space
                no-error-icon
                emit-value
                map-options
                class="producto-form__control"
                @update:model-value="cambioTalla(variante, i)"
              />

              <q-select
                v-model="variante.color_id"
                :options="opcionesColores"
                :aria-label="`Color de la variante ${i + 1}`"
                :error="Boolean(errorDe(i, 'color_id'))"
                :error-message="errorDe(i, 'color_id')"
                dense
                outlined
                hide-bottom-space
                no-error-icon
                emit-value
                map-options
                class="producto-form__control"
                @update:model-value="cambioColor(variante, i)"
              >
                <template #prepend>
                  <span
                    class="producto-form__swatch"
                    :style="{ background: colorPorId.get(variante.color_id)?.hexadecimal ?? 'transparent' }"
                  />
                </template>
                <template #option="scope">
                  <q-item v-bind="scope.itemProps">
                    <q-item-section side>
                      <span
                        class="producto-form__swatch"
                        :style="{ background: scope.opt.hexadecimal }"
                      />
                    </q-item-section>
                    <q-item-section>{{ scope.opt.label }}</q-item-section>
                  </q-item>
                </template>
              </q-select>

              <q-input
                :model-value="variante.sku"
                :aria-label="`SKU de la variante ${i + 1}`"
                :error="Boolean(errorDe(i, 'sku'))"
                :error-message="errorDe(i, 'sku')"
                dense
                outlined
                hide-bottom-space
                no-error-icon
                maxlength="40"
                class="producto-form__control producto-form__sku"
                @update:model-value="escribirSku(variante, $event)"
                @change="form.validate(`${PATH}.variantes.${i}.sku`)"
              >
                <template
                  v-if="variante.skuManual"
                  #append
                >
                  <q-btn
                    flat
                    dense
                    round
                    size="xs"
                    icon="autorenew"
                    tabindex="-1"
                    :aria-label="`Volver al SKU sugerido en la variante ${i + 1}`"
                    @click="restaurarSku(variante, i)"
                  >
                    <q-tooltip>Volver al SKU sugerido</q-tooltip>
                  </q-btn>
                </template>
              </q-input>

              <q-input
                v-model="variante.precio"
                :aria-label="`Precio de la variante ${i + 1}`"
                :placeholder="formatearPrecio(form.producto.precio) || 'Base'"
                :error="Boolean(errorDe(i, 'precio'))"
                :error-message="errorDe(i, 'precio')"
                type="number"
                min="0"
                step="0.01"
                dense
                outlined
                hide-bottom-space
                no-error-icon
                class="producto-form__control"
                @change="form.validate(`${PATH}.variantes.${i}.precio`)"
              />

              <!-- Miniatura + cantidad; abre el panel de fotos debajo de la fila. -->
              <button
                type="button"
                :class="['producto-form__fotosBtn', {
                  'producto-form__fotosBtn--abierto': abierta === variante.uid,
                  'producto-form__fotosBtn--error': erroresDe(`${PATH}.variantes.${i}.archivos`).length
                }]"
                :aria-expanded="String(abierta === variante.uid)"
                :aria-label="`Fotos de la variante ${i + 1} (${variante.archivos.length})`"
                @click="abierta = abierta === variante.uid ? null : variante.uid"
              >
                <img
                  v-if="variante.archivos.length"
                  :src="variante.archivos[0].miniatura_url ?? variante.archivos[0].url"
                  alt=""
                  width="40"
                  height="40"
                  class="producto-form__fotosThumb"
                >
                <q-icon
                  v-else
                  name="add_a_photo"
                  size="16px"
                />
                <span
                  v-if="variante.archivos.length"
                  class="producto-form__fotosCount"
                >{{ variante.archivos.length }}</span>
              </button>

              <span class="producto-form__stock text-mono">{{ variante.stock }}</span>

              <!-- Con stock no se quita: desaparecería mercadería (el backend
                   también lo rechaza). -->
              <q-btn
                flat
                dense
                round
                icon="close"
                size="sm"
                color="grey-7"
                :disable="variante.stock !== 0"
                :aria-label="`Quitar variante ${i + 1}`"
                @click="quitar(i)"
              >
                <q-tooltip v-if="variante.stock !== 0">
                  Tiene stock: no se puede quitar
                </q-tooltip>
              </q-btn>
            </div>

            <div
              v-if="abierta === variante.uid"
              class="producto-form__panelFotos"
            >
              <AppFotos
                v-model="variante.archivos"
                :label="`Fotos de ${etiquetaDe(variante)}`"
                :errores="erroresDe(`${PATH}.variantes.${i}.archivos`)"
              />
              <AppButton
                v-if="variante.archivos.length && hermanasDeColor(variante).length"
                variant="tertiary"
                icon="content_copy"
                :label="`Usar estas fotos en las otras ${hermanasDeColor(variante).length} tallas de ${colorPorId.get(variante.color_id)?.nombre}`"
                @click="copiarFotosAlColor(variante)"
              />
            </div>
          </template>
        </div>
      </div>

      <p
        v-else
        class="producto-form__vacio"
      >
        Todavía no hay variantes. Elegí tallas y colores arriba y tocá “Agregar combinaciones”.
      </p>
    </section>

    <!-- ── Medidas ── -->
    <section
      v-if="tallasUsadas.length"
      class="producto-form__seccion"
    >
      <header>
        <h3 class="producto-form__title">
          Medidas por talla (cm)
        </h3>
        <p class="producto-form__hint">
          Se cargan una vez por talla y valen para todos sus colores.
        </p>
      </header>

      <div class="producto-form__medidasAgregar">
        <q-input
          v-model="nuevaMedida"
          label="Nueva medida"
          placeholder="Largo, Pecho…"
          maxlength="30"
          dense
          outlined
          class="producto-form__control producto-form__medidaInput"
          @keydown.enter.prevent="agregarMedida(nuevaMedida)"
        />
        <AppButton
          label="Agregar"
          icon="add"
          :disable="!nuevaMedida.trim()"
          @click="agregarMedida(nuevaMedida)"
        />
        <q-chip
          v-for="sugerida in sugerenciasMedidas"
          :key="sugerida"
          clickable
          dense
          outline
          icon="add"
          :label="sugerida"
          @click="agregarMedida(sugerida)"
        />
      </div>

      <div
        v-if="nombresMedidas.length"
        class="producto-form__tablaWrap"
      >
        <table class="producto-form__medidas">
          <thead>
            <tr>
              <th scope="col">
                Talla
              </th>
              <th
                v-for="nombre in nombresMedidas"
                :key="nombre"
                scope="col"
              >
                <span class="producto-form__medidaNombre">
                  {{ nombre }}
                  <q-btn
                    flat
                    dense
                    round
                    size="xs"
                    icon="close"
                    :aria-label="`Quitar la medida ${nombre}`"
                    @click="quitarMedida(nombre)"
                  />
                </span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="talla in tallasUsadas"
              :key="talla.id"
            >
              <th scope="row">
                {{ talla.nombre }}
              </th>
              <td
                v-for="nombre in nombresMedidas"
                :key="nombre"
              >
                <q-input
                  :model-value="medidaDe(talla.id, nombre)"
                  :aria-label="`${nombre} de la talla ${talla.nombre}, en cm`"
                  type="number"
                  min="0"
                  step="0.1"
                  dense
                  outlined
                  hide-bottom-space
                  class="producto-form__control"
                  @update:model-value="ponerMedida(talla.id, nombre, $event)"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p
        v-for="mensaje in erroresMedidas"
        :key="mensaje"
        class="producto-form__error"
        role="alert"
      >
        {{ mensaje }}
      </p>
    </section>

    <button
      type="submit"
      hidden
    />
  </form>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue'
import { useForm } from 'laravel-precognition-vue'
import AppButton from '@/components/AppButton.vue'
import AppFotos from '@/components/AppFotos.vue'
import AppTextField from '@/components/AppTextField.vue'
import CategoriaService from '@/services/CategoriaService'
import ColorService from '@/services/ColorService'
import ProductoService from '@/services/ProductoService'
import TallaService from '@/services/TallaService'
import { formatearPrecio } from '@/utils/moneda'
import { opcionesPadre } from '@/modules/Categorias/arbol'
import formProducto, { nuevaVariante } from './FormProducto'
import { sugerirSku } from './sku'

const PATH = 'producto'
const SUGERENCIAS_MEDIDAS = ['Largo', 'Pecho', 'Manga', 'Cintura', 'Cadera', 'Tiro']

const props = defineProps({
  // null = crear; con id = editar.
  id: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['save'])

const uid = `producto-${useId()}`

// Editar va por POST + _method=PUT (ver FormProducto.js).
const form = props.id
  ? useForm('post', `api/productos/${props.id}`, () => formProducto(true))
  : useForm('post', 'api/productos', () => formProducto())

// ── Catálogos ──
const categorias = ref([])
const tallas = ref([])
const colores = ref([])
const cargando = ref(true)

const tallaPorId = computed(() => new Map(tallas.value.map((t) => [t.id, t])))
const colorPorId = computed(() => new Map(colores.value.map((c) => [c.id, c])))

// Todas las categorías, con la ruta completa ("Ropa › Niños › Polos").
const opcionesCategorias = computed(() => opcionesPadre(categorias.value))
const busquedaCategoria = ref('')
const categoriasFiltradas = computed(() => {
  const termino = busquedaCategoria.value.toLowerCase()
  return termino
    ? opcionesCategorias.value.filter((o) => o.label.toLowerCase().includes(termino))
    : opcionesCategorias.value
})

function filtrarCategorias (valor, update) {
  update(() => { busquedaCategoria.value = valor })
}

// Tallas ya vienen en su orden de exhibición desde la API.
const opcionesTallas = computed(() => tallas.value.map((t) => ({ value: t.id, label: t.nombre })))
const opcionesColores = computed(() => colores.value.map((c) => ({ value: c.id, label: c.nombre, hexadecimal: c.hexadecimal })))

// ── Errores ──
function errorDe (i, campo) {
  return form.errors[`${PATH}.variantes.${i}.${campo}`]
}

// Todos los mensajes bajo un prefijo (la galería y cada una de sus fotos).
function erroresDe (prefijo) {
  return [...new Set(Object.entries(form.errors)
    .filter(([clave]) => clave === prefijo || clave.startsWith(`${prefijo}.`))
    .map(([, mensaje]) => mensaje))]
}

// ── SKU sugerido ──
function skuSugerido (variante) {
  return sugerirSku(
    form.producto.nombre,
    tallaPorId.value.get(variante.talla_id)?.nombre,
    colorPorId.value.get(variante.color_id)?.nombre
  )
}

function refrescarSku (variante) {
  if (!variante.skuManual) variante.sku = skuSugerido(variante)
}

// Renombrar el producto arrastra los SKU que no se tocaron a mano.
watch(() => form.producto.nombre, () => form.producto.variantes.forEach(refrescarSku))

function escribirSku (variante, valor) {
  variante.sku = String(valor ?? '').toUpperCase()
  // Borrarlo del todo vuelve al sugerido.
  variante.skuManual = variante.sku !== '' && variante.sku !== skuSugerido(variante)
  if (!variante.sku) refrescarSku(variante)
}

function restaurarSku (variante, i) {
  variante.skuManual = false
  refrescarSku(variante)
  form.validate(`${PATH}.variantes.${i}.sku`)
}

// Cambiar de talla trae las medidas de la nueva talla.
function cambioTalla (variante, i) {
  variante.medidas = { ...medidasDeTalla(variante.talla_id, variante) }
  refrescarSku(variante)
  form.validate(`${PATH}.variantes.${i}.color_id`)
}

function cambioColor (variante, i) {
  refrescarSku(variante)
  form.validate(`${PATH}.variantes.${i}.color_id`)
}

function etiquetaDe (variante) {
  const talla = tallaPorId.value.get(variante.talla_id)?.nombre ?? '—'
  const color = colorPorId.value.get(variante.color_id)?.nombre ?? '—'
  return `talla ${talla} · ${color}`
}

// ── Generador de combinaciones ──
const tallasElegidas = ref([])
const coloresElegidos = ref([])

function agregarCombinaciones () {
  const existentes = new Set(form.producto.variantes.map((v) => `${v.talla_id}-${v.color_id}`))

  // En el orden de los catálogos, no en el orden en que se tildaron.
  const tallasOrdenadas = tallas.value.filter((t) => tallasElegidas.value.includes(t.id))
  const coloresOrdenados = colores.value.filter((c) => coloresElegidos.value.includes(c.id))

  for (const talla of tallasOrdenadas) {
    for (const color of coloresOrdenados) {
      if (existentes.has(`${talla.id}-${color.id}`)) continue

      const variante = nuevaVariante({
        talla_id: talla.id,
        color_id: color.id,
        // Si la talla ya tenía medidas cargadas, la nueva variante las hereda.
        medidas: { ...medidasDeTalla(talla.id) },
        // Y si el color ya tenía fotos en otra talla, también.
        archivos: fotosDeColor(color.id)
      })
      refrescarSku(variante)
      form.producto.variantes.push(variante)
    }
  }

  tallasElegidas.value = []
  coloresElegidos.value = []
}

function quitar (i) {
  if (abierta.value === form.producto.variantes[i].uid) abierta.value = null
  form.producto.variantes.splice(i, 1)
}

// ── Fotos por variante ──
// La variante con el panel de fotos abierto (una a la vez).
const abierta = ref(null)

function hermanasDeColor (variante) {
  return form.producto.variantes.filter((v) => v !== variante && v.color_id === variante.color_id)
}

function fotosDeColor (colorId) {
  const conFotos = form.producto.variantes.find((v) => v.color_id === colorId && v.archivos.length)
  return conFotos ? copiarFotos(conFotos.archivos) : []
}

// Una guardada viaja por id (el backend duplica el archivo); una nueva viaja
// como el mismo File otra vez. Objetos nuevos: cada galería edita su lista.
function copiarFotos (archivos) {
  return archivos.map((foto) => ({ ...foto }))
}

function copiarFotosAlColor (variante) {
  hermanasDeColor(variante).forEach((hermana) => {
    hermana.archivos = copiarFotos(variante.archivos)
  })
}

// ── Medidas por talla ──
const nombresMedidas = ref([])
const nuevaMedida = ref('')

const tallasUsadas = computed(() => {
  const ids = new Set(form.producto.variantes.map((v) => v.talla_id).filter(Boolean))
  return tallas.value.filter((t) => ids.has(t.id))
})

const sugerenciasMedidas = computed(() => SUGERENCIAS_MEDIDAS.filter((m) => !nombresMedidas.value.includes(m)))

function variantesDeTalla (tallaId) {
  return form.producto.variantes.filter((v) => v.talla_id === tallaId)
}

function medidasDeTalla (tallaId, excepto = null) {
  return variantesDeTalla(tallaId).find((v) => v !== excepto)?.medidas ?? {}
}

function medidaDe (tallaId, nombre) {
  return medidasDeTalla(tallaId)[nombre] ?? ''
}

function ponerMedida (tallaId, nombre, valor) {
  const texto = valor === null || valor === undefined ? '' : String(valor)

  variantesDeTalla(tallaId).forEach((variante) => {
    const medidas = { ...variante.medidas }
    if (texto === '') delete medidas[nombre]
    else medidas[nombre] = texto
    variante.medidas = medidas
  })
}

function agregarMedida (nombre) {
  const limpio = String(nombre ?? '').trim()
  nuevaMedida.value = ''
  if (!limpio) return

  const yaExiste = nombresMedidas.value.some((m) => m.toLowerCase() === limpio.toLowerCase())
  if (!yaExiste) nombresMedidas.value.push(limpio)
}

function quitarMedida (nombre) {
  nombresMedidas.value = nombresMedidas.value.filter((m) => m !== nombre)
  form.producto.variantes.forEach((variante) => {
    const medidas = { ...variante.medidas }
    delete medidas[nombre]
    variante.medidas = medidas
  })
}

const erroresMedidas = computed(() => [...new Set(Object.entries(form.errors)
  .filter(([clave]) => /\.medidas(\.|$)/.test(clave))
  .map(([, mensaje]) => mensaje))])

// ── Carga ──
// Lo que da ArchivoResource; al guardar viaja sólo el id (lo demás se ignora).
function aFoto ({ id, url, miniatura_url: miniaturaUrl, nombre, ancho, alto }) {
  return { id, url, miniatura_url: miniaturaUrl, nombre, ancho, alto }
}

onMounted(async () => {
  const todos = { params: { rowsPerPage: 0 } }

  const [catalogoCategorias, catalogoTallas, catalogoColores, producto] = await Promise.all([
    CategoriaService.getData(todos),
    TallaService.getData(todos),
    ColorService.getData({ params: { rowsPerPage: 0, order_by: 'nombre' } }),
    props.id ? ProductoService.get(props.id) : null
  ])

  categorias.value = catalogoCategorias.data
  tallas.value = catalogoTallas.data
  colores.value = catalogoColores.data
  cargando.value = false

  if (!producto) return

  const { nombre, categoria_id: categoriaId, descripcion, precio, activo, archivos, variantes } = producto

  form.setData({
    _method: 'PUT',
    [PATH]: {
      nombre,
      categoria_id: categoriaId,
      descripcion: descripcion ?? '',
      precio: String(precio),
      activo,
      archivos: archivos.map(aFoto),
      variantes: variantes.map((v) => nuevaVariante({
        id: v.id,
        talla_id: v.talla_id,
        color_id: v.color_id,
        sku: v.sku,
        precio: v.precio ?? '',
        stock: v.stock,
        // Los inputs trabajan con texto.
        medidas: Object.fromEntries(Object.entries(v.medidas ?? {}).map(([k, val]) => [k, String(val)])),
        archivos: v.archivos.map(aFoto),
        // Si el SKU guardado no es el que se sugeriría, lo escribieron a mano.
        skuManual: v.sku !== sugerirSku(nombre, v.talla?.nombre, v.color?.nombre)
      }))
    }
  })

  // Columnas de medidas: todas las que tenga alguna variante.
  nombresMedidas.value = [...new Set(variantes.flatMap((v) => Object.keys(v.medidas ?? {})))]
})

// Las vistas previas de fotos nuevas (blob:) se liberan al cerrar el form.
// Acá y no en AppFotos: la misma foto puede estar copiada en varias galerías.
onBeforeUnmount(() => {
  const todas = [...form.producto.archivos, ...form.producto.variantes.flatMap((v) => v.archivos)]
  new Set(todas.map((f) => f.url).filter((url) => url?.startsWith('blob:')))
    .forEach((url) => URL.revokeObjectURL(url))
})

async function submit () {
  try {
    await form.submit()
    form.reset()
    emit('save')
  } catch {
    // 422: los errores quedan en form.errors y se ven en cada campo.
  }
}

defineExpose({ form, submit })
</script>

<style lang="scss" scoped>
.producto-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.producto-form__row {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;

  &--end {
    align-items: flex-end;
  }
}

.producto-form__grow {
  flex: 1 1 240px;
  min-width: 0;
}

.producto-form__precio {
  flex: 0 1 200px;
}

.producto-form__toggle {
  padding-bottom: 4px;
  font-size: 13.5px;
  color: var(--app-ink);
}

.producto-form__field {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.producto-form__label {
  font-size: 12.5px;
  font-weight: 600;
  letter-spacing: -0.1px;
  color: var(--app-ink);
}

.producto-form__icon {
  color: var(--app-ink-2);
}

// Mismo radio, borde y foco que AppTextField.
.producto-form__control {
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

// ── Secciones ──
.producto-form__seccion {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-top: 16px;
  border-top: 1px solid var(--app-border-subtle);
}

.producto-form__title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  line-height: 1.3;
  color: var(--app-ink);
}

.producto-form__count {
  padding: 1px 8px;
  border-radius: 999px;
  font-size: 12px;
  background: var(--app-border-subtle);
  color: var(--app-ink-2);
}

.producto-form__hint,
.producto-form__vacio {
  margin: 4px 0 0;
  font-size: 12px;
  line-height: 1.5;
  color: var(--app-ink-2);
}

.producto-form__vacio {
  padding: 16px;
  border: 1px dashed var(--app-border-control);
  border-radius: 10px;
  text-align: center;
}

.producto-form__error {
  margin: 0;
  font-size: 12px;
  color: var(--q-negative);
}

.producto-form__generador {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 12px;
}

// En pantallas angostas la tabla se desplaza sola en vez de apretarse.
.producto-form__tablaWrap {
  overflow-x: auto;
}

.producto-form__tabla {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 740px;
}

.producto-form__fila {
  display: grid;
  grid-template-columns: 1fr 1.4fr 1.8fr 1.1fr 48px 48px 36px;
  align-items: start;
  gap: 8px;

  &--head {
    font-size: 12px;
    font-weight: 600;
    color: var(--app-ink-2);
  }
}

.producto-form__sku :deep(input) {
  font-family: $font-mono;
  text-transform: uppercase;
}

.producto-form__stock {
  padding-top: 9px;
  text-align: right;
  color: var(--app-ink-2);
}

.producto-form__swatch {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 4px;
}

// ── Fotos por variante ──
.producto-form__fotosBtn {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  padding: 0;
  border: 1px solid var(--app-border-control);
  border-radius: 10px;
  overflow: hidden;
  background: var(--app-surface);
  color: var(--app-ink-2);
  cursor: pointer;

  &:hover {
    border-color: var(--app-border-control-hover);
  }

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 2px;
  }

  &--abierto {
    border-color: $primary;
    box-shadow: 0 0 0 3px rgba($primary, 0.12);
  }

  &--error {
    border-color: var(--q-negative);
  }
}

.producto-form__fotosThumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.producto-form__fotosCount {
  position: absolute;
  right: 2px;
  bottom: 2px;
  min-width: 16px;
  padding: 0 4px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 600;
  line-height: 16px;
  background: rgba(0, 0, 0, 0.6);
  color: #FFFFFF;
}

.producto-form__panelFotos {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
  padding: 12px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 10px;
}

// ── Medidas ──
.producto-form__medidasAgregar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.producto-form__medidaInput {
  width: 200px;
}

.producto-form__medidas {
  border-collapse: separate;
  border-spacing: 8px 6px;
  margin: 0 -8px;

  th {
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    color: var(--app-ink-2);
    white-space: nowrap;
  }

  tbody th {
    color: var(--app-ink);
  }

  td {
    min-width: 90px;
  }
}

.producto-form__medidaNombre {
  display: inline-flex;
  align-items: center;
  gap: 2px;
}
</style>
