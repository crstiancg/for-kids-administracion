import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

const InventarioService = vi.hoisted(() => ({ getData: vi.fn() }))
vi.mock('@/services/InventarioService', () => ({ default: InventarioService }))
vi.mock('@/boot/axios', () => ({ api: {} }))

import MovimientosProducto from '@/modules/Inventario/MovimientosProducto.vue'

const PAGINA = {
  data: [
    {
      id: 31,
      tipo: 'salida',
      cantidad: -2,
      stock_resultante: 6,
      costo_unitario: null,
      motivo_label: 'Merma',
      referencia: null,
      observacion: null,
      fecha: '2026-10-06T15:30:00-05:00',
      usuario: { id: 1, name: 'Administrador' },
      variante: { sku: 'POLO-4-BLA', talla: '4', color: { nombre: 'Blanco', hexadecimal: '#FFFFFF' } }
    },
    {
      id: 30,
      tipo: 'entrada',
      cantidad: 8,
      stock_resultante: 8,
      costo_unitario: '12.00',
      motivo_label: null,
      referencia: 'F001-123',
      observacion: null,
      fecha: '2026-10-05T10:00:00-05:00',
      usuario: null,
      variante: { sku: 'POLO-4-BLA', talla: '4', color: { nombre: 'Blanco', hexadecimal: '#FFFFFF' } }
    }
  ],
  total: 2
}

beforeEach(() => {
  InventarioService.getData.mockReset()
  InventarioService.getData.mockResolvedValue(PAGINA)
})

describe('MovimientosProducto', () => {
  it('pide sólo los movimientos del producto, del más nuevo al más viejo', async () => {
    mount(MovimientosProducto, { props: { productoId: 36 } })
    await flushPromises()

    expect(InventarioService.getData).toHaveBeenCalledWith({
      params: expect.objectContaining({ producto_id: 36, order_by: '-id', page: 1 })
    })
  })

  it('muestra cantidad con signo, variante, stock resultante y detalle', async () => {
    const wrapper = mount(MovimientosProducto, { props: { productoId: 36 } })
    await flushPromises()

    const texto = wrapper.text()
    expect(texto).toContain('+8')
    expect(texto).toContain('-2')
    expect(texto).toContain('POLO-4-BLA')
    expect(texto).toContain('Merma')
    expect(texto).toContain('Ref. F001-123')
    expect(texto).toContain('Administrador')
  })

  it('cuando el producto cambia (p. ej. tras editarlo) vuelve a pedir', async () => {
    const wrapper = mount(MovimientosProducto, { props: { productoId: 36 } })
    await flushPromises()

    await wrapper.vm.recargar()
    await flushPromises()

    expect(InventarioService.getData).toHaveBeenCalledTimes(2)
  })
})
