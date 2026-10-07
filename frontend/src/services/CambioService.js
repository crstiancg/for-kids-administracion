import { api } from '@/boot/axios'

class CambioService {
  // Qué puede volver de cada línea, a qué valor, saldo del cliente y plazo.
  static async preparar (pedidoId) {
    return (await api.get(`api/pedidos/${pedidoId}/cambios/preparar`)).data
  }

  static async registrar (pedidoId, cambio) {
    return (await api.post(`api/pedidos/${pedidoId}/cambios`, { cambio })).data
  }
}

export default CambioService
