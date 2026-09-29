<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Caja"
      :subtitle="subtitulo"
    >
      <template #actions>
        <AppButton
          v-if="userStore.hasPermission('cajas.index')"
          variant="tertiary"
          label="Historial"
          icon="history"
          to="/cajas"
        />
        <template v-if="caja">
          <AppButton
            v-if="userStore.hasPermission('cajas.movimientos')"
            label="Ingreso"
            icon="add"
            @click="abrirMovimiento('ingreso')"
          />
          <AppButton
            v-if="userStore.hasPermission('cajas.movimientos')"
            label="Egreso"
            icon="remove"
            @click="abrirMovimiento('egreso')"
          />
          <AppButton
            v-if="userStore.hasPermission('cajas.cerrar')"
            variant="primary"
            label="Cerrar caja"
            icon="lock"
            @click="cerrarDialog = true"
          />
        </template>
      </template>
    </AppPageHeader>

    <div
      v-if="cargando"
      class="caja__cargando"
    >
      <q-spinner size="28px" />
    </div>

    <!-- Sin caja abierta: abrirla es lo primero del día. -->
    <AppCard
      v-else-if="!caja"
      class="caja__cerrada"
    >
      <h2 class="caja__titulo">
        No hay una caja abierta
      </h2>
      <p class="caja__texto">
        Para cobrar pedidos (en cualquier método) tiene que haber una caja abierta.
      </p>
      <AbrirCajaForm
        v-if="userStore.hasPermission('cajas.abrir')"
        @save="abierta"
      />
      <p
        v-else
        class="caja__texto"
      >
        Pedile a quien tenga permiso que la abra.
      </p>
    </AppCard>

    <CajaResumen
      v-else
      :caja="caja"
    />

    <AppDialog
      v-model="movimientoDialog"
      :title="tipoMovimiento === 'egreso' ? 'Registrar egreso' : 'Registrar ingreso'"
      persistent
    >
      <MovimientoCajaForm
        v-if="movimientoDialog"
        ref="movimientoRef"
        :tipo="tipoMovimiento"
        @save="movimientoGuardado"
      />
      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="movimientoDialog = false"
        />
        <AppButton
          variant="primary"
          label="Registrar"
          :loading="movimientoRef?.form.processing"
          @click="movimientoRef.submit()"
        />
      </template>
    </AppDialog>

    <AppDialog
      v-model="cerrarDialog"
      title="Cerrar caja"
      persistent
    >
      <CerrarCajaForm
        v-if="cerrarDialog && caja"
        ref="cerrarRef"
        :caja="caja"
        @save="cerrada"
      />
      <template #actions>
        <AppButton
          variant="tertiary"
          label="Volver"
          @click="cerrarDialog = false"
        />
        <AppButton
          variant="primary"
          label="Cerrar caja"
          :loading="cerrarRef?.form.processing"
          @click="cerrarRef.submit()"
        />
      </template>
    </AppDialog>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import AppButton from '@/components/AppButton.vue'
import AppCard from '@/components/AppCard.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import CajaService from '@/services/CajaService'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'
import AbrirCajaForm from './AbrirCajaForm.vue'
import CajaResumen from './CajaResumen.vue'
import CerrarCajaForm from './CerrarCajaForm.vue'
import MovimientoCajaForm from './MovimientoCajaForm.vue'

const $q = useQuasar()
const userStore = useUserStore()

const caja = ref(null)
const cargando = ref(true)

const formatoFecha = new Intl.DateTimeFormat('es-PE', { dateStyle: 'short', timeStyle: 'short' })

const subtitulo = computed(() => {
  if (!caja.value) return 'Cerrada'
  const quien = caja.value.abierta_por?.name
  return `Abierta desde ${formatoFecha.format(new Date(caja.value.abierta_at))}${quien ? ` por ${quien}` : ''}`
})

async function cargar () {
  cargando.value = true
  try {
    caja.value = await CajaService.actual()
  } finally {
    cargando.value = false
  }
}

onMounted(cargar)

function abierta (nueva) {
  caja.value = nueva
  $q.notify({ type: 'positive', message: 'Caja abierta.', position: 'top-right', timeout: 1500 })
}

// ── Ingresos / egresos ──
const movimientoDialog = ref(false)
const movimientoRef = ref()
const tipoMovimiento = ref('ingreso')

function abrirMovimiento (tipo) {
  tipoMovimiento.value = tipo
  movimientoDialog.value = true
}

function movimientoGuardado (actualizada) {
  movimientoDialog.value = false
  caja.value = actualizada
  $q.notify({ type: 'positive', message: 'Movimiento registrado.', position: 'top-right', timeout: 1500 })
}

// ── Cierre ──
const cerrarDialog = ref(false)
const cerrarRef = ref()

function cerrada (resultado) {
  cerrarDialog.value = false
  caja.value = null
  const diferencia = Number(resultado?.diferencia ?? 0)
  $q.notify({
    type: diferencia === 0 ? 'positive' : 'warning',
    message: diferencia === 0
      ? 'Caja cerrada: cuadra exacto.'
      : `Caja cerrada con ${diferencia < 0 ? 'faltante' : 'sobrante'} de ${formatearPrecio(Math.abs(diferencia))}.`,
    position: 'top-right',
    timeout: 4000
  })
}
</script>

<style lang="scss" scoped>
.caja__cargando {
  display: flex;
  justify-content: center;
  padding: 48px;
}

.caja__cerrada {
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-width: 520px;
}

.caja__titulo {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  color: var(--app-ink);
}

.caja__texto {
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
  color: var(--app-ink-2);
}
</style>
