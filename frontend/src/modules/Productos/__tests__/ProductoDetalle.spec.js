import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

const ProductoService = vi.hoisted(() => ({ get: vi.fn() }))
vi.mock('@/services/ProductoService', () => ({ default: ProductoService }))
vi.mock('@/boot/axios', () => ({ api: {} }))

const push = vi.hoisted(() => vi.fn())
vi.mock('vue-router', async (importOriginal) => ({ ...(await importOriginal()), useRouter: () => ({ push }) }))

import ProductoDetalle from '@/modules/Productos/ProductoDetalle.vue'
import { useUserStore } from '@/stores/user-store'

const PRODUCTO = {
  id: 7,
  nombre: 'Polo básico algodón',
  categoria: { id: 3, nombre: 'Polos' },
  descripcion: 'Polo de algodón peinado.',
  precio: '25.00',
  activo: true,
  archivos: [{ id: 1, url: '/f/1.jpg', miniatura_url: '/f/1-min.webp' }],
  variantes: [
    { id: 11, sku: 'POLO-4-BLA', codigo_barras: '2000000000114', precio: null, stock: 8, talla: { nombre: '4' }, color: { nombre: 'Blanco', hexadecimal: '#FFFFFF' }, archivos: [] },
    { id: 12, sku: 'POLO-6-NEG', codigo_barras: '2000000000121', precio: '29.00', stock: 0, talla: { nombre: '6' }, color: { nombre: 'Negro', hexadecimal: '#111827' }, archivos: [] }
  ]
}

function montar (permisos = ['productos.index', 'productos.update', 'etiquetas.imprimir']) {
  setActivePinia(createPinia())
  useUserStore().permisos = permisos
  return mount(ProductoDetalle, {
    props: { id: 7 },
    global: { stubs: { ProductosForm: true, MovimientosProducto: true, MovimientoForm: true, ProductoSelector: true, RouterLink: true, 'router-link': true } }
  })
}

beforeEach(() => {
  push.mockReset()
  ProductoService.get.mockReset()
  ProductoService.get.mockResolvedValue(PRODUCTO)
})

describe('ProductoDetalle', () => {
  it('pide el producto por id y muestra nombre, categoría y descripción', async () => {
    const wrapper = montar()
    await flushPromises()

    expect(ProductoService.get).toHaveBeenCalledWith(7)
    expect(wrapper.text()).toContain('Polo básico algodón')
    expect(wrapper.text()).toContain('Polos')
    expect(wrapper.text()).toContain('Polo de algodón peinado.')
  })

  it('lista las variantes con SKU, código de barras y stock', async () => {
    const wrapper = montar()
    await flushPromises()

    expect(wrapper.text()).toContain('POLO-4-BLA')
    expect(wrapper.text()).toContain('2000000000121')
    expect(wrapper.findAll('[data-test="variante"]')).toHaveLength(2)
  })

  it('una variante sin precio propio muestra el precio base', async () => {
    const wrapper = montar()
    await flushPromises()

    const [primera, segunda] = wrapper.findAll('[data-test="variante"]')
    expect(primera.text()).toMatch(/25\.00/)
    expect(segunda.text()).toMatch(/29\.00/)
  })

  it('resume el stock total y avisa las variantes agotadas', async () => {
    const wrapper = montar()
    await flushPromises()

    expect(wrapper.find('[data-test="stock-total"]').text()).toContain('8')
    expect(wrapper.find('[data-test="agotadas"]').text()).toContain('1')
  })

  it('con permiso de inventario muestra los movimientos del producto', async () => {
    const wrapper = montar(['productos.index', 'inventario.index'])
    await flushPromises()

    const movimientos = wrapper.findComponent({ name: 'MovimientosProducto' })
    expect(movimientos.exists()).toBe(true)
    expect(movimientos.props('productoId')).toBe(7)
  })

  it('sin permiso de inventario no muestra los movimientos', async () => {
    const wrapper = montar(['productos.index'])
    await flushPromises()

    expect(wrapper.findComponent({ name: 'MovimientosProducto' }).exists()).toBe(false)
  })

  it('muestra la portada como avatar en el encabezado', async () => {
    const wrapper = montar()
    await flushPromises()

    expect(wrapper.find('[data-test="avatar"]').attributes('src')).toBe('/f/1-min.webp')
  })

  it('cada movimiento de inventario aparece sólo con su permiso', async () => {
    const wrapper = montar(['productos.index', 'inventario.index', 'inventario.entradas', 'inventario.ajustes'])
    await flushPromises()

    expect(wrapper.find('[data-test="mov-entrada"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="mov-salida"]').exists()).toBe(false)
    expect(wrapper.find('[data-test="mov-ajuste"]').exists()).toBe(true)
  })

  it('abre el movimiento con las variantes del producto', async () => {
    const wrapper = montar(['productos.index', 'inventario.index', 'inventario.entradas'])
    await flushPromises()

    await wrapper.find('[data-test="mov-entrada"]').trigger('click')
    await flushPromises()

    const form = wrapper.findComponent({ name: 'MovimientoForm' })
    expect(form.props('tipo')).toBe('entrada')
    expect(form.props('productoId')).toBe(7)
  })

  it('elegir otro producto en el selector navega a su detalle', async () => {
    const wrapper = montar()
    await flushPromises()

    wrapper.findComponent({ name: 'ProductoSelector' }).vm.$emit('elegir', 37)

    expect(push).toHaveBeenCalledWith('/productos/37')
  })

  it('sin permiso de edición no muestra el botón Editar', async () => {
    const wrapper = montar(['productos.index'])
    await flushPromises()

    expect(wrapper.find('[data-test="editar"]').exists()).toBe(false)
  })
})
