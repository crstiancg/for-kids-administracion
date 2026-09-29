/**
 * Tipos de movimiento: lo que ve el usuario y a qué endpoint va cada uno.
 * Mismo mapeo único que Pedidos/Productos: label, status del AppChip e ícono
 * nacen juntos, así nunca se desincronizan.
 */
export const TIPOS = {
  entrada: {
    label: 'Entrada',
    titulo: 'Registrar entrada',
    ayuda: 'Compra o reposición. El costo recalcula el costo promedio de cada variante.',
    endpoint: 'entradas',
    permiso: 'inventario.entradas',
    status: 'positive',
    icon: 'add_box'
  },
  salida: {
    label: 'Salida',
    titulo: 'Registrar salida',
    ayuda: 'Merma, daño, regalo… Las ventas descontarán solas desde Pedidos.',
    endpoint: 'salidas',
    permiso: 'inventario.salidas',
    status: 'negative',
    icon: 'indeterminate_check_box'
  },
  ajuste: {
    label: 'Ajuste',
    titulo: 'Ajuste por conteo',
    ayuda: 'Escribí el stock que contaste: el sistema registra la diferencia. Las variantes que coinciden no generan movimiento.',
    endpoint: 'ajustes',
    permiso: 'inventario.ajustes',
    status: 'info',
    icon: 'fact_check'
  }
}

// Mismas claves que MovimientoInventario::MOTIVOS_SALIDA del backend.
export const MOTIVOS_SALIDA = [
  { value: 'merma', label: 'Merma' },
  { value: 'danado', label: 'Dañado' },
  { value: 'regalo', label: 'Regalo / promoción' },
  { value: 'devolucion_proveedor', label: 'Devolución al proveedor' },
  { value: 'uso_interno', label: 'Uso interno' },
  { value: 'otro', label: 'Otro' }
]
