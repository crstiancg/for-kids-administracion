<template>
  <div class="app-filter-bar">
    <q-input
      v-model="search"
      dense
      outlined
      :placeholder="searchPlaceholder"
      class="app-filter-bar__search"
    >
      <template #prepend>
        <q-icon name="search" />
      </template>
    </q-input>

    <!-- Acá van los AppFilterPill (u otro filtro) de quien use la barra:
         la cantidad y el tipo de filtros varían por página, la barra sólo
         les da el layout y el buscador. -->
    <slot />

    <div
      v-if="hasActiveFilters"
      class="app-filter-bar__clear"
      @click="$emit('clear')"
    >
      Limpiar filtros
    </div>
  </div>
</template>

<script setup>
defineProps({
  searchPlaceholder: {
    type: String,
    default: 'Buscar…'
  },

  // La calcula quien usa la barra: es quien sabe si además del texto hay
  // otros filtros activos (estado, categoría, lo que venga por el slot).
  hasActiveFilters: {
    type: Boolean,
    default: false
  }
})

defineEmits(['clear'])

const search = defineModel('search', { default: '' })
</script>

<style lang="scss" scoped>
.app-filter-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.app-filter-bar__search {
  width: 264px;

  :deep(.q-field__control) {
    height: 40px;
    border-radius: 9px;
  }

  :deep(.q-field__control):before {
    border-color: var(--app-border-control);
  }
}

.app-filter-bar__clear {
  margin-left: auto;
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink-2);
  cursor: pointer;

  &:hover {
    color: var(--app-ink);
  }
}

@media (max-width: 599px) {
  .app-filter-bar__search {
    width: 100%;
  }
}
</style>
