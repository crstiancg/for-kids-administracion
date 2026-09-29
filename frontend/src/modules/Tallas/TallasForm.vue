<template>
  <form
    class="talla-form"
    novalidate
    @submit.prevent="submit"
  >
    <AppTextField
      v-model="form.talla.nombre"
      label="Nombre"
      icon="straighten"
      placeholder="8, XL, 3-6M…"
      maxlength="20"
      :error="form.errors[`${PATH}.nombre`]"
      :loading="form.validating"
      autofocus
      @change="form.validate(`${PATH}.nombre`)"
    />

    <AppTextField
      v-model="form.talla.orden"
      label="Orden"
      icon="sort"
      type="number"
      min="0"
      placeholder="10"
      :error="form.errors[`${PATH}.orden`]"
      @change="form.validate(`${PATH}.orden`)"
    />

    <p class="talla-form__hint">
      Define en qué posición aparece la talla en listas y selectores (de menor a mayor).
      Conviene numerar de 10 en 10 para poder intercalar después.
    </p>

    <button
      type="submit"
      hidden
    />
  </form>
</template>

<script setup>
import { onMounted } from 'vue'
import { useForm } from 'laravel-precognition-vue'
import AppTextField from '@/components/AppTextField.vue'
import TallaService from '@/services/TallaService'
import formTalla from './FormTalla'

const PATH = 'talla'

const props = defineProps({
  // null = crear; con id = editar.
  id: {
    type: Number,
    default: null
  },
  // Orden sugerido al crear: el siguiente después de la última talla.
  ordenSugerido: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['save'])

const form = props.id
  ? useForm('put', `api/tallas/${props.id}`, formTalla)
  : useForm('post', 'api/tallas', formTalla)

onMounted(async () => {
  if (!props.id) {
    // String: AppTextField trabaja con strings; el backend valida integer igual.
    if (props.ordenSugerido !== null) form.talla.orden = String(props.ordenSugerido)
    return
  }

  const { nombre, orden } = await TallaService.get(props.id)
  form.setData({ [PATH]: { nombre, orden: String(orden) } })
})

async function submit () {
  try {
    await form.submit()
    form.reset()
    emit('save')
  } catch {
    // 422: los errores quedan en form.errors y se ven debajo de cada campo.
  }
}

defineExpose({ form, submit })
</script>

<style lang="scss" scoped>
.talla-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.talla-form__hint {
  margin: -8px 0 0;
  font-size: 12px;
  line-height: 1.5;
  color: var(--app-ink-2);
}
</style>
