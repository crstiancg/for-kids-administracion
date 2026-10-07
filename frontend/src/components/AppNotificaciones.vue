<template>
  <q-btn
    flat
    dense
    round
    :icon="avisos.length ? 'notifications' : 'notifications_none'"
    :aria-label="avisos.length ? `Notificaciones: ${avisos.length}` : 'Notificaciones: no hay avisos'"
    class="campana"
  >
    <!-- El número sólo existe si hay algo que atender: una campana que
         siempre está en rojo enseña a ignorarla. -->
    <q-badge
      v-if="avisos.length"
      floating
      rounded
      :color="hayCritico ? 'negative' : 'warning'"
      :label="avisos.length"
      class="campana__badge"
    />

    <q-menu
      anchor="bottom right"
      self="top right"
      :offset="[0, 8]"
      class="campana__menu"
      @before-show="cargar"
    >
      <div class="campana__head">
        Notificaciones
      </div>
      <q-separator />
      <q-list
        v-if="avisos.length"
        class="campana__lista"
      >
        <q-item
          v-for="aviso in avisos"
          :key="aviso.clave"
          v-close-popup
          clickable
          :to="aviso.ruta"
        >
          <q-item-section avatar>
            <span :class="['campana__icono', `campana__icono--${aviso.nivel}`]">
              <q-icon
                :name="aviso.icono"
                size="18px"
              />
            </span>
          </q-item-section>
          <q-item-section>
            <q-item-label class="campana__titulo">
              {{ aviso.titulo }}
            </q-item-label>
            <q-item-label caption>
              {{ aviso.detalle }}
            </q-item-label>
          </q-item-section>
        </q-item>
      </q-list>
      <div
        v-else
        class="campana__vacio"
      >
        <q-icon
          name="check_circle"
          size="22px"
        />
        Todo en orden: nada pendiente.
      </div>
    </q-menu>
  </q-btn>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import NotificacionService from '@/services/NotificacionService'

/**
 * Los avisos se calculan en el servidor del estado actual (caja sin cerrar,
 * pedidos demorados, agotados, ofertas por vencer): desaparecen solos al
 * resolverse. Se refrescan al navegar, al abrir el menú y cada 2 minutos.
 */
const CADA = 2 * 60 * 1000

const route = useRoute()
const avisos = ref([])
const hayCritico = computed(() => avisos.value.some((a) => a.nivel === 'critico'))

async function cargar () {
  try {
    avisos.value = await NotificacionService.avisos()
  } catch {
    // Una campana no vale un error en pantalla.
  }
}

let timer
onMounted(() => {
  cargar()
  timer = setInterval(cargar, CADA)
})
onBeforeUnmount(() => clearInterval(timer))

// Resolver algo (cerrar la caja, confirmar un pedido) suele terminar
// navegando: se recalcula enseguida.
watch(() => route.path, cargar)
</script>

<style lang="scss" scoped>
.campana {
  color: var(--app-ink-2);
}

.campana__badge {
  top: 2px;
  right: 0;
  font-size: 10px;
  font-weight: 700;
}

:global(.campana__menu) {
  width: 340px;
  max-width: calc(100vw - 32px);
  border-radius: 12px;
}

.campana__head {
  padding: 12px 16px;
  font-size: 14px;
  font-weight: 700;
  color: var(--app-ink);
}

.campana__lista {
  padding: 6px 0;
}

.campana__titulo {
  font-weight: 600;
  line-height: 1.35;
}

.campana__icono {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 999px;

  &--critico {
    background: rgba($negative, 0.12);
    color: var(--q-negative);
  }

  &--aviso {
    background: rgba($warning, 0.15);
    color: var(--q-warning);
  }

  &--info {
    background: var(--app-brand-soft);
    color: var(--app-brand-soft-ink);
  }
}

.campana__vacio {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 24px 16px;
  font-size: 13px;
  color: var(--app-ink-2);
}
</style>
