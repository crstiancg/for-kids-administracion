import { api } from '@/boot/axios'

class ReporteService {
  // params: { periodo: 'hoy'|'semana'|'mes'|'rango', desde?, hasta? }
  static async get (params = {}) {
    return (await api.get('api/reportes', { params })).data
  }
}

export default ReporteService
