/**
 * Forma inicial del form de tallas. La clave `talla` es la misma que valida
 * StoreTallaRequest (`talla.nombre`, `talla.orden`).
 *
 * Función y no objeto: useForm guarda estos datos como "originales" para el
 * reset(), y un objeto compartido arrastraría lo tipeado al siguiente form.
 */
export default function formTalla () {
  return {
    talla: {
      nombre: '',
      orden: ''
    }
  }
}
