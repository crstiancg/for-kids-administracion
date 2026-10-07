import { describe, it, expect } from 'vitest'
import { costoInicialTotal, nuevaVariante } from '@/modules/Productos/FormProducto'

describe('costoInicialTotal', () => {
  it('suma unidades por el costo PROPIO de cada variante nueva', () => {
    const lista = [
      nuevaVariante({ stock_inicial: '4', costo_unitario: '10' }), // talla chica
      nuevaVariante({ stock_inicial: '2', costo_unitario: '14' }), // talla grande, más cara
      nuevaVariante({ id: 9, stock_inicial: '50', costo_unitario: '99' }) // existente: no cuenta
    ]

    expect(costoInicialTotal(lista)).toBe(68)
  })
})
