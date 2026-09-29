/**
 * Forma inicial del form de pedidos. La clave `pedido` es la misma que valida
 * StorePedidoRequest (`pedido.items.0.cantidad`…). Los totales NO van: los
 * calcula el backend; acá se muestran sólo como vista previa.
 *
 * Función y no objeto: useForm guarda estos datos como "originales" para el
 * reset(), y un objeto compartido arrastraría lo cargado al siguiente form.
 */
export default function formPedido () {
  return {
    pedido: {
      // null = "Cliente varios".
      cliente_id: null,
      canal: 'mostrador',
      descuento: '',
      observacion: '',
      items: []
    }
  }
}

/**
 * Un ítem para la variante elegida, con su precio de venta actual como
 * propuesta. `variante` viaja sólo para mostrar (el backend usa variante_id).
 *
 * @param {{ id: number, precio: string|null }} variante
 */
export function nuevoItem (variante) {
  return {
    variante_id: variante.id,
    variante,
    cantidad: '1',
    precio_unitario: variante.precio !== null && variante.precio !== undefined
      ? Number(variante.precio).toFixed(2)
      : ''
  }
}
