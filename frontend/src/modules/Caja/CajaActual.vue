<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Mi caja"
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
          <!-- Vencida no recibe dinero: sólo se cierra. -->
          <AppButton
            v-if="userStore.hasPermission('cajas.movimientos') && !caja.vencida"
            label="Ingreso"
            icon="add"
            @click="abrirMovimiento('ingreso')"
          />
          <AppButton
            v-if="userStore.hasPermission('cajas.movimientos') && !caja.vencida"
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

    <!-- Sin caja abierta. Se abre desde el punto de venta, que es donde se
         empieza a vender: acá sólo se indica el camino. El form queda para
         quien cobra pedidos pero no usa el POS. -->
    <AppCard
      v-else-if="!caja"
      class="caja__cerrada"
    >
      <span class="caja__icono">
        <q-icon
          name="lock"
          size="26px"
        />
      </span>
      <h2 class="caja__titulo">
        Tu caja está cerrada
      </h2>
      <p class="caja__texto">
        Se abre al empezar a vender, desde el punto de venta, con el efectivo
        inicial del cajón. Lo que cobres hoy entra a tu caja y al cerrar se
        arquea sólo tu cajón.
      </p>
      <AppButton
        v-if="userStore.hasPermission('ventas.store')"
        variant="primary"
        label="Ir al punto de venta"
        icon="point_of_sale"
        to="/pos"
      />
      <AbrirCajaForm
        v-else-if="userStore.hasPermission('cajas.abrir')"
        class="caja__form"
        @save="abierta"
      />
      <p
        v-else
        class="caja__texto"
      >
        No tenés permiso para abrir caja: pedile a un encargado.
      </p>
    </AppCard>

    <template v-else>
      <div
        v-if="caja.vencida"
        class="caja__vencida"
        role="alert"
      >
        <q-icon
          name="warning"
          size="20px"
        />
        <span>
          Esta caja es del <strong>{{ diaApertura }}</strong>. La caja es diaria:
          cerrala con su arqueo para poder abrir la de hoy y seguir cobrando.
        </span>
      </div>
      <CajaResumen :caja="caja" />
    </template>

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
  return `Abierta desde ${formatoFecha.format(new Date(caja.value.abierta_at))}`
})

const diaApertura = computed(() => caja.value
  ? new Intl.DateTimeFormat('es-PE', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date(caja.value.abierta_at))
  : '')

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
.caja__vencida {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 16px;
  padding: 12px 14px;
  border: 1px solid rgba($warning, 0.5);
  border-radius: 12px;
  background: rgba($warning, 0.1);
  font-size: 13.5px;
  line-height: 1.5;
  color: var(--app-ink);
}

.caja__cargando {
  display: flex;
  justify-content: center;
  padding: 48px;
}

.caja__cerrada {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  max-width: 460px;
  padding: 36px 28px;
  text-align: center;
}

.caja__icono {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 56px;
  height: 56px;
  margin-bottom: 4px;
  border-radius: 999px;
  background: var(--app-brand-soft);
  color: var(--app-brand-soft-ink);
}

.caja__form {
  width: 100%;
  text-align: left;
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
