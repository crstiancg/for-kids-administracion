import { api } from '@/boot/axios'

class DashboardService {
  // Sólo vienen los bloques que el usuario tiene permiso de ver.
  static async get () {
    return (await api.get('api/dashboard')).data
  }
}

export default DashboardService
