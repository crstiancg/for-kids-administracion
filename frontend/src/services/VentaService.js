import { api } from '@/boot/axios'

class VentaService {
  /**
   * Catálogo del punto de venta (paginado): productos activos con portada,
   * stock, vendidos y variantes. Filtros: search, categoria_id, talla_id,
   * color_id, con_stock, order_by (vendidos | nombre | precio | -precio | stock).
   */
  static async catalogo (config) {
    return (await api.get('api/ventas/catalogo', config)).data
  }

  /**
   * Venta de mostrador: crea el pedido, lo confirma, lo cobra y lo entrega
   * en una sola transacción. Devuelve el pedido completo (para el ticket).
   */
  static async registrar (venta) {
    return (await api.post('api/ventas', { venta })).data
  }
}

export default VentaService
