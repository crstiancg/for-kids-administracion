import { api } from '@/boot/axios'

/**
 * Crear y editar no pasan por acá: los hace el form con useForm() de
 * Precognition, que valida y envía contra la misma URL.
 */
class TallaService {
  static async getData (config) {
    return (await api.get('api/tallas', config)).data
  }

  static async get (id) {
    return (await api.get(`api/tallas/${id}`)).data
  }

  static async delete (id) {
    return api.delete(`api/tallas/${id}`)
  }
}

export default TallaService
