/**
 * SKU sugerido de una variante: "Polo básico" + talla "8" + color "Rojo"
 * → "POLO-BAS-8-ROJ". Es sólo una sugerencia: el form lo deja editar y el
 * backend valida que sea único y de letras, números y guiones.
 */

// Sin tildes, en mayúsculas y con cualquier otro símbolo como espacio.
function limpiar (texto) {
  return String(texto ?? '')
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toUpperCase()
    .replace(/[^A-Z0-9]+/g, ' ')
    .trim()
}

function palabras (texto) {
  return limpiar(texto).split(' ').filter(Boolean)
}

// Primera palabra hasta 4 letras, segunda hasta 3: "POLO-BAS".
function parteProducto (nombre) {
  return palabras(nombre)
    .slice(0, 2)
    .map((palabra, i) => palabra.slice(0, i === 0 ? 4 : 3))
    .join('-')
}

// "Azul" → "AZU", "Azul marino" → "AZUM": sin la inicial de la segunda
// palabra, "Azul" y "Azul marino" darían el mismo SKU.
function parteColor (nombre) {
  const [primera = '', segunda = ''] = palabras(nombre)
  return primera.slice(0, 3) + segunda.slice(0, 1)
}

/**
 * @param {string} producto nombre del producto
 * @param {string} talla nombre de la talla
 * @param {string} color nombre del color
 * @returns {string} '' si todavía no hay datos para armarlo
 */
export function sugerirSku (producto, talla, color) {
  return [parteProducto(producto), palabras(talla).join(''), parteColor(color)]
    .filter(Boolean)
    .join('-')
}
