import { api } from '@/boot/axios'

/**
 * Registrar entradas, salidas y ajustes no pasa por acá: lo hace el form con
 * useForm() de Precognition, que valida y envía contra la misma URL.
 */
class InventarioService {
  // Historial de movimientos (paginado, filtrable).
  static async getData (config) {
    return (await api.get('api/inventario', config)).data
  }

  // Buscador de variantes para las líneas de un movimiento.
  static async variantes (config) {
    return (await api.get('api/inventario/variantes', config)).data
  }
}

export default InventarioService
