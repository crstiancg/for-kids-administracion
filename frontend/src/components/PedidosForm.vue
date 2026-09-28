<template>
  <div class="pedidos-form">
    <q-input
      v-model="model.cliente"
      dense
      outlined
      label="Cliente"
      class="pedidos-form__field"
    />

    <div class="pedidos-form__row">
      <q-select
        v-model="model.canal"
        dense
        outlined
        emit-value
        map-options
        :options="canalOptions"
        label="Canal"
        class="pedidos-form__field"
      />

      <q-select
        v-model="model.estado"
        dense
        outlined
        emit-value
        map-options
        :options="estadoOptions"
        label="Estado"
        class="pedidos-form__field"
      />
    </div>

    <q-input
      v-model="model.total"
      dense
      outlined
      label="Total"
      prefix="$"
      class="pedidos-form__field"
    />
  </div>
</template>

<script setup>
// Listas propias del formulario, sin el "Todos" que sí necesitan los
// filtros de la tabla: acá cada opción es un valor real que puede guardarse.
const CANALES = ['Búsqueda orgánica', 'Campañas pagas', 'Referidos', 'Tráfico directo']
const ESTADOS = ['Completado', 'Pendiente', 'En proceso', 'Cancelado']

const canalOptions = CANALES.map((canal) => ({ label: canal, value: canal }))
const estadoOptions = ESTADOS.map((estado) => ({ label: estado, value: estado }))

// El padre (Pedidos) es dueño del borrador: éste sólo lo edita. Así puede
// leerlo para armar la fila final y resetearlo al cerrar el diálogo.
const model = defineModel({
  default: () => ({ cliente: '', canal: null, estado: null, total: '' })
})
</script>

<style lang="scss" scoped>
.pedidos-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.pedidos-form__row {
  display: flex;
  gap: 16px;
}

// Mismo radio y borde que el resto de los campos del sistema (buscador,
// pastillas de filtro): el outlined default de Quasar viene en 4px.
.pedidos-form__field {
  flex: 1;

  :deep(.q-field__control) {
    border-radius: 9px;
  }

  :deep(.q-field__control):before {
    border-color: var(--app-border-control);
  }
}

@media (max-width: 599px) {
  .pedidos-form__row {
    flex-direction: column;
  }
}
</style>
