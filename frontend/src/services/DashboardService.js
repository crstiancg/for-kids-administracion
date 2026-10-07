import { api } from '@/boot/axios'

class DashboardService {
  // Sólo vienen los bloques que el usuario tiene permiso de ver.
  // params: { periodo: 'hoy'|'semana'|'mes'|'rango', desde?, hasta? }
  static async get (params = {}) {
    return (await api.get('api/dashboard', { params })).data
  }
}

export default DashboardService
