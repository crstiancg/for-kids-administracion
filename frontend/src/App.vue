<template>
  <router-view />
</template>

<script setup>
import { useQuasar } from 'quasar'
import { modoOscuroGuardado } from '@/components/SwitchDarkMode.vue'

// El modo oscuro elegido se aplica antes de pintar cualquier pantalla.
const $q = useQuasar()
const oscuro = modoOscuroGuardado()
if (oscuro !== null) $q.dark.set(oscuro)
</script>
