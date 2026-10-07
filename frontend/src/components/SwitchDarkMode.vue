<template>
  <q-toggle
    v-model="oscuro"
    dense
    color="primary"
    size="lg"
    checked-icon="mdi-moon-waxing-crescent"
    unchecked-icon="mdi-white-balance-sunny"
    :aria-label="oscuro ? 'Desactivar modo oscuro' : 'Activar modo oscuro'"
  />
</template>

<script>
// Preferencia de ESTE navegador: se recuerda entre recargas. Puede no haber
// storage (modo privado, bloqueado): entonces simplemente no se recuerda.
const CLAVE = 'for-kids:modo-oscuro'

export function modoOscuroGuardado () {
  try {
    const valor = localStorage.getItem(CLAVE)
    return valor === null ? null : valor === '1'
  } catch {
    return null
  }
}

function guardar (oscuro) {
  try {
    localStorage.setItem(CLAVE, oscuro ? '1' : '0')
  } catch {
    // Sin storage: el cambio vale hasta recargar.
  }
}
</script>

<script setup>
import { computed } from 'vue'
import { useQuasar } from 'quasar'

const $q = useQuasar()

// Lee y escribe directo en Quasar: si hay dos switches (login y header)
// nunca quedan desincronizados.
const oscuro = computed({
  get: () => $q.dark.isActive,
  set: (valor) => {
    $q.dark.set(valor)
    guardar(valor)
  }
})
</script>
