<template>
  <form
    class="usuario-form"
    novalidate
    @submit.prevent="submit"
  >
    <!-- ── Datos de la cuenta ── -->
    <div class="usuario-form__grid">
      <AppTextField
        v-model="form.usuario.name"
        label="Nombre completo"
        icon="person_outline"
        placeholder="Ana Pérez"
        :error="form.errors[`${PATH}.name`]"
        autofocus
        @change="form.validate(`${PATH}.name`)"
      />

      <AppTextField
        v-model="form.usuario.username"
        label="Usuario"
        icon="alternate_email"
        placeholder="aperez"
        autocomplete="off"
        :error="form.errors[`${PATH}.username`]"
        :loading="form.validating"
        @change="form.validate(`${PATH}.username`)"
      />

      <AppTextField
        v-model="form.usuario.email"
        label="Email (opcional)"
        type="email"
        icon="mail_outline"
        placeholder="ana@forkids.com"
        autocomplete="off"
        :error="form.errors[`${PATH}.email`]"
        @change="form.validate(`${PATH}.email`)"
      />

      <AppTextField
        v-model="form.usuario.password"
        label="Contraseña"
        type="password"
        icon="lock_outline"
        autocomplete="new-password"
        :placeholder="id ? '••••••••' : 'Mínimo 8 caracteres'"
        :error="form.errors[`${PATH}.password`]"
        @change="form.validate(`${PATH}.password`)"
      >
        <template
          v-if="id"
          #aside
        >
          <span class="usuario-form__hint">Dejala vacía para no cambiarla</span>
        </template>
      </AppTextField>
    </div>

    <!-- ── Accesos: roles + permisos directos ── -->
    <div class="usuario-form__grid">
      <section class="usuario-roles">
        <div class="usuario-form__sectionHead">
          <span class="usuario-form__sectionTitle">Roles</span>
          <span class="usuario-form__count">{{ form.usuario.rolesSelected.length }} de {{ roles.length }}</span>
        </div>

        <div
          class="usuario-roles__list"
          role="group"
          aria-label="Roles del usuario"
        >
          <div
            v-for="rol in roles"
            :key="rol.id"
            class="usuario-roles__item"
          >
            <q-checkbox
              v-model="form.usuario.rolesSelected"
              :val="rol.id"
              :label="rol.name"
              dense
            />
          </div>

          <div
            v-if="!cargando && roles.length === 0"
            class="usuario-form__empty"
          >
            Todavía no hay roles creados.
          </div>
        </div>

        <div
          v-if="form.errors[`${PATH}.rolesSelected`]"
          class="usuario-form__error"
        >
          {{ form.errors[`${PATH}.rolesSelected`] }}
        </div>
      </section>

      <PermisosChecklist
        v-model="form.usuario.permisosSelected"
        titulo="Permisos directos"
        :permisos="permisos"
        :cargando="cargando"
        :error="form.errors[`${PATH}.permisosSelected`]"
      />
    </div>

    <!-- ── Heredados (sólo lectura) ── -->
    <section class="usuario-heredados">
      <div class="usuario-form__sectionHead">
        <span class="usuario-form__sectionTitle">Permisos heredados de los roles</span>
        <span class="usuario-form__count">{{ heredados.total }}</span>
      </div>

      <div
        v-if="heredados.roles.length === 0"
        class="usuario-heredados__empty"
      >
        Tildá un rol para ver los permisos que le da al usuario.
      </div>

      <template v-else>
        <div
          v-for="rol in heredados.roles"
          :key="rol.id"
          class="usuario-heredados__rol"
        >
          <div class="usuario-heredados__rolName">
            {{ rol.name }}
            <span class="usuario-form__count">{{ rol.permissions.length }}</span>
          </div>

          <div class="usuario-heredados__permisos">
            <span
              v-for="permiso in rol.permissions"
              :key="permiso.id"
              :class="['usuario-heredados__permiso', { 'usuario-heredados__permiso--directo': form.usuario.permisosSelected.includes(permiso.id) }]"
              :title="permiso.description"
            >
              <q-icon
                v-if="form.usuario.permisosSelected.includes(permiso.id)"
                name="check"
                size="14px"
              />
              {{ permiso.name }}
            </span>

            <span
              v-if="rol.permissions.length === 0"
              class="usuario-form__hint"
            >
              Este rol no tiene permisos.
            </span>
          </div>
        </div>

        <p class="usuario-form__hint">
          Se editan desde el rol. Los marcados con <q-icon
            name="check"
            size="12px"
          /> también están asignados de forma directa.
        </p>
      </template>
    </section>

    <button
      type="submit"
      hidden
    />
  </form>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useForm } from 'laravel-precognition-vue'
import AppTextField from '@/components/AppTextField.vue'
import PermisosChecklist from '@/modules/Permisos/PermisosChecklist.vue'
import PermisoService from '@/services/PermisoService'
import RolService from '@/services/RolService'
import UsuarioService from '@/services/UsuarioService'
import formUsuario from './FormUsuario'

const PATH = 'usuario'

const props = defineProps({
  // null = crear; con id = editar.
  id: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['save'])

const form = props.id
  ? useForm('put', `api/usuarios/${props.id}`, formUsuario)
  : useForm('post', 'api/usuarios', formUsuario)

// ── Catálogos ──
const roles = ref([])
const permisos = ref([])
const cargando = ref(true)

// Los permisos que el usuario recibe por sus roles. Sólo lectura: un permiso
// heredado se quita desde el rol, no desde acá.
const heredados = computed(() => {
  const seleccionados = roles.value
    .filter((rol) => form.usuario.rolesSelected.includes(rol.id))
    .map((rol) => ({ ...rol, permissions: rol.permissions ?? [] }))

  const unicos = new Set(seleccionados.flatMap((rol) => rol.permissions.map((p) => p.id)))

  return { roles: seleccionados, total: unicos.size }
})

onMounted(async () => {
  const params = { params: { rowsPerPage: 0, order_by: 'name' } }

  const [catalogoRoles, catalogoPermisos, usuario] = await Promise.all([
    RolService.getData(params),
    PermisoService.getData(params),
    props.id ? UsuarioService.get(props.id) : null
  ])

  roles.value = catalogoRoles.data
  permisos.value = catalogoPermisos.data
  cargando.value = false

  if (usuario) {
    const { name, username, email } = usuario.user
    form.setData({
      [PATH]: {
        name,
        username,
        email: email ?? '',
        password: '',
        rolesSelected: usuario.rolesSelected,
        permisosSelected: usuario.permisosSelected
      }
    })
  }
})

async function submit () {
  try {
    await form.submit()
    form.reset()
    emit('save')
  } catch {
    // 422: los errores quedan en form.errors y se muestran en cada campo.
  }
}

defineExpose({ form, submit })
</script>

<style lang="scss" scoped>
.usuario-form {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.usuario-form__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px 20px;
  align-items: start;
}

.usuario-form__sectionHead {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  margin-bottom: 8px;
}

.usuario-form__sectionTitle {
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);
}

.usuario-form__count {
  font-family: $font-mono;
  font-size: 12px;
  color: var(--app-ink-2);
}

.usuario-form__hint {
  margin: 0;
  font-size: 12px;
  color: var(--app-ink-2);
}

.usuario-form__empty {
  padding: 20px 12px;
  font-size: 13px;
  text-align: center;
  color: var(--app-ink-2);
}

.usuario-form__error {
  margin-top: 6px;
  font-size: 12px;
  color: var(--q-negative);
}

// ── Roles ──
.usuario-roles__list {
  max-height: 346px;
  overflow-y: auto;
  border: 1px solid var(--app-border-subtle);
  border-radius: 10px;
}

.usuario-roles__item {
  padding: 10px 12px;
  font-size: 13.5px;
  color: var(--app-ink);

  & + & {
    border-top: 1px solid var(--app-border-subtle);
  }

  &:hover {
    background: var(--app-page);
  }

  :deep(.q-checkbox) {
    width: 100%;
  }

  :deep(.q-checkbox__label) {
    padding-left: 10px;
  }
}

// ── Heredados ──
.usuario-heredados {
  padding: 16px;
  border-radius: 10px;
  background: var(--app-page);
}

.usuario-heredados__empty {
  font-size: 13px;
  color: var(--app-ink-2);
}

.usuario-heredados__rol + .usuario-heredados__rol {
  margin-top: 14px;
}

.usuario-heredados__rolName {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin-bottom: 6px;
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);
}

.usuario-heredados__permisos {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 12px;
}

.usuario-heredados__permiso {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 9px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 999px;
  background: var(--app-surface);
  font-family: $font-mono;
  font-size: 11.5px;
  color: var(--app-ink-2);

  // Heredado Y directo: se ve distinto porque quitarle el rol no le saca
  // este permiso al usuario.
  &--directo {
    border-color: transparent;
    background: var(--app-brand-soft);
    color: var(--app-brand-soft-ink);
  }
}

@media (max-width: 719px) {
  .usuario-form__grid {
    grid-template-columns: 1fr;
  }
}
</style>
