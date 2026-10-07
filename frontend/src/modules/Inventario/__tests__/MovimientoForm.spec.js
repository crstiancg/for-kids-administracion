import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { reactive } from 'vue'

const InventarioService = vi.hoisted(() => ({ variantes: vi.fn() }))
vi.mock('@/services/InventarioService', () => ({ default: InventarioService }))
vi.mock('@/boot/axios', () => ({ api: {} }))

const useForm = vi.hoisted(() => vi.fn())
vi.mock('laravel-precognition-vue', () => ({ useForm }))

import MovimientoForm from '@/modules/Inventario/MovimientoForm.vue'

let form
function fakeForm (inputs) {
  form = reactive({
    ...inputs,
    errors: {},
    processing: false,
    validate: vi.fn(),
    submit: vi.fn(() => Promise.resolve({ data: { data: [] } })),
    reset: vi.fn()
  })
  return form
}

const VARIANTES = [
  { id: 11, sku: 'POLO-4-BLA', stock: 8, costo_promedio: '12.0000', talla: '4', color: { nombre: 'Blanco' }, producto: { nombre: 'Polo' } },
  { id: 12, sku: 'POLO-6-NEG', stock: 0, costo_promedio: null, talla: '6', color: { nombre: 'Negro' }, producto: { nombre: 'Polo' } }
]

beforeEach(() => {
  InventarioService.variantes.mockReset()
  InventarioService.variantes.mockResolvedValue({ data: VARIANTES })
  useForm.mockReset()
  useForm.mockImplementation((method, url, inputs) => fakeForm(typeof inputs === 'function' ? inputs() : inputs))
})

const stubs = { BuscadorVariante: true, AppTextField: true }

describe('MovimientoForm', () => {
  it('sin producto arranca vacío y no busca variantes', async () => {
    mount(MovimientoForm, { props: { tipo: 'entrada' }, global: { stubs } })
    await flushPromises()

    expect(InventarioService.variantes).not.toHaveBeenCalled()
    expect(form.movimiento.lineas).toEqual([])
  })

  it('con producto precarga todas sus variantes como líneas', async () => {
    mount(MovimientoForm, { props: { tipo: 'ajuste', productoId: 36 }, global: { stubs } })
    await flushPromises()

    expect(InventarioService.variantes).toHaveBeenCalledWith({ params: { producto_id: 36, rowsPerPage: 0 } })
    expect(form.movimiento.lineas.map((l) => l.variante_id)).toEqual([11, 12])
    // Ajuste: arranca con el stock del sistema.
    expect(form.movimiento.lineas[0].stock_real).toBe('8')
  })

  it('con producto, una entrada descarta al enviar las líneas sin cantidad', async () => {
    const wrapper = mount(MovimientoForm, { props: { tipo: 'entrada', productoId: 36 }, global: { stubs } })
    await flushPromises()
    form.movimiento.lineas[1].cantidad = '5'

    await wrapper.vm.submit()

    expect(form.movimiento.lineas.map((l) => l.variante_id)).toEqual([12])
    expect(form.submit).toHaveBeenCalled()
  })

  it('sin producto no descarta nada: la línea vacía la marca el backend', async () => {
    const wrapper = mount(MovimientoForm, { props: { tipo: 'salida' }, global: { stubs } })
    await flushPromises()
    wrapper.findComponent({ name: 'BuscadorVariante' }).vm.$emit('elegir', VARIANTES[0])

    await wrapper.vm.submit()

    expect(form.movimiento.lineas).toHaveLength(1)
  })
})
