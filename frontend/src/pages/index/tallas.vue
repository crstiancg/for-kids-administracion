<template>
  <q-page>
    <TallasList />
  </q-page>
</template>

<script setup>
import TallasList from '@/modules/Tallas/TallasList.vue'

definePage({ meta: { permiso: 'tallas.index' } })
</script>
