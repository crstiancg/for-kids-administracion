<template>
  <q-page>
    <ProductoDetalle :id="Number(route.params.id)" />
  </q-page>
</template>

<script setup>
import { useRoute } from 'vue-router'
import ProductoDetalle from '@/modules/Productos/ProductoDetalle.vue'

definePage({ meta: { permiso: 'productos.index' } })

const route = useRoute()
</script>
