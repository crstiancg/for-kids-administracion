import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

const ProductoService = vi.hoisted(() => ({ getData: vi.fn() }))
vi.mock('@/services/ProductoService', () => ({ default: ProductoService }))
vi.mock('@/boot/axios', () => ({ api: {} }))

import ProductoSelector from '@/modules/Productos/ProductoSelector.vue'

const PRODUCTOS = [
  { id: 36, nombre: 'Polo básico', categoria: { nombre: 'Polos' }, precio: '25.00', stock_total: 40, activo: true, portada: null },
  { id: 37, nombre: 'Polo estampado', categoria: { nombre: 'Polos' }, precio: '32.00', stock_total: 0, activo: false, portada: { miniatura_url: '/m.webp' } }
]

beforeEach(() => {
  ProductoService.getData.mockReset()
  ProductoService.getData.mockResolvedValue({ data: PRODUCTOS, total: 2 })
})

function filtrar (wrapper, valor) {
  const update = vi.fn((fn) => fn())
  wrapper.findComponent({ name: 'QSelect' }).vm.$emit('filter', valor, update, vi.fn())
  return update
}

describe('ProductoSelector', () => {
  it('busca en el servidor por nombre o SKU, con portada, categoría y stock', async () => {
    const wrapper = mount(ProductoSelector, { props: { actual: 36 } })

    const update = filtrar(wrapper, ' polo ')
    await flushPromises()

    expect(ProductoService.getData).toHaveBeenCalledWith({ params: { search: 'polo', rowsPerPage: 15, order_by: 'nombre' } })
    expect(update).toHaveBeenCalled()
  })

  it('al elegir otro producto emite su id', async () => {
    const wrapper = mount(ProductoSelector, { props: { actual: 36 } })

    wrapper.findComponent({ name: 'QSelect' }).vm.$emit('update:model-value', PRODUCTOS[1])

    expect(wrapper.emitted('elegir')).toEqual([[37]])
  })

  it('elegir el mismo producto no emite nada', async () => {
    const wrapper = mount(ProductoSelector, { props: { actual: 36 } })

    wrapper.findComponent({ name: 'QSelect' }).vm.$emit('update:model-value', PRODUCTOS[0])

    expect(wrapper.emitted('elegir')).toBeUndefined()
  })
})
