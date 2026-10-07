import { api } from '@/boot/axios'

class NotificacionService {
  // Avisos calculados del estado actual: vacío = no hay nada que atender.
  static async avisos () {
    return (await api.get('api/notificaciones')).data.avisos
  }
}

export default NotificacionService
