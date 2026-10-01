/**
 * Forma inicial del form de productos. La clave `producto` es la misma que
 * valida StoreProductoRequest (`producto.nombre`, `producto.variantes.0.sku`…).
 *
 * Función y no objeto: useForm guarda estos datos como "originales" para el
 * reset(), y un objeto compartido arrastraría lo tipeado al siguiente form.
 *
 * Al editar va como POST con `_method: 'PUT'`: con fotos nuevas viaja en
 * multipart, y PHP no parsea multipart en un PUT (llegaría vacío).
 *
 * @param {boolean} editando
 */
export default function formProducto (editando = false) {
  return {
    ...(editando ? { _method: 'PUT' } : {}),
    producto: {
      nombre: '',
      categoria_id: null,
      descripcion: '',
      precio: '',
      activo: true,
      // Stock inicial de las variantes nuevas: entra como una entrada de
      // inventario ("Alta de producto") con este costo (ajustable por variante).
      costo_compra: '',
      referencia_compra: '',
      // Fotos: { id, url, nombre } guardadas o { archivo: File, url, nombre } nuevas.
      archivos: [],
      variantes: []
    }
  }
}

let ultimoUid = 0

/**
 * Una fila de variante. `uid` es la key del v-for (las nuevas no tienen id)
 * y `skuManual` marca que el SKU lo escribió el usuario: desde ahí deja de
 * regenerarse al cambiar nombre, talla o color. Ninguno de los dos lo valida
 * el backend, así que no se guardan. `stock` es sólo para mostrar.
 *
 * `medidas` es { Largo: '52', Pecho: '40' } en cm: el form las edita por
 * talla y las copia a todas las variantes de esa talla.
 */
export function nuevaVariante (datos = {}) {
  return {
    uid: ++ultimoUid,
    id: null,
    talla_id: null,
    color_id: null,
    sku: '',
    precio: '',
    stock: 0,
    // Sólo variantes nuevas: unidades que entran al crearla y su costo si
    // difiere del general de la compra.
    stock_inicial: '',
    costo_unitario: '',
    con_movimientos: false,
    medidas: {},
    archivos: [],
    skuManual: false,
    ...datos
  }
}
