/**
 * Forma inicial del documento de inventario. La clave `movimiento` es la
 * misma que valida MovimientoInventarioRequest (`movimiento.lineas.0.cantidad`…).
 *
 * Función y no objeto: useForm guarda estos datos como "originales" para el
 * reset(), y un objeto compartido arrastraría lo cargado al siguiente form.
 *
 * @param {'entrada'|'salida'|'ajuste'} tipo
 */
export default function formMovimiento (tipo) {
  return {
    movimiento: {
      referencia: '',
      observacion: '',
      ...(tipo === 'salida' ? { motivo: null } : {}),
      lineas: []
    }
  }
}

/**
 * Una línea para la variante elegida. `variante` viaja sólo para mostrar
 * (producto, talla, color, stock): el backend valida y usa `variante_id`.
 *
 * @param {'entrada'|'salida'|'ajuste'} tipo
 * @param {{ id: number, stock: number, costo_promedio: string|null }} variante
 */
export function nuevaLinea (tipo, variante) {
  const linea = { variante_id: variante.id, variante }

  if (tipo === 'entrada') {
    linea.cantidad = ''
    // Se propone el costo promedio actual; casi siempre la compra se repite.
    linea.costo_unitario = variante.costo_promedio !== null
      ? Number(variante.costo_promedio).toFixed(2)
      : ''
  } else if (tipo === 'salida') {
    linea.cantidad = ''
  } else {
    // Ajuste: arranca con el stock del sistema; se corrige lo que difiera.
    linea.stock_real = String(variante.stock)
  }

  return linea
}
