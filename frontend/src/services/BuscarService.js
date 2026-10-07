import { api } from '@/boot/axios'

class BuscarService {
  // Productos, pedidos y clientes a la vez (sólo los grupos con permiso).
  static async buscar (q) {
    return (await api.get('api/buscar', { params: { q } })).data.resultados
  }
}

export default BuscarService
