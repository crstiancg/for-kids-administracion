/**
 * Datos de la tienda para el encabezado del ticket. Se configuran en el .env
 * del front (VITE_APP_TIENDA_*); sin configurar, sale sólo el nombre.
 */
const env = import.meta.env

export const TIENDA = {
  nombre: env.VITE_APP_TIENDA_NOMBRE || 'For Kids',
  ruc: env.VITE_APP_TIENDA_RUC || '',
  direccion: env.VITE_APP_TIENDA_DIRECCION || '',
  telefono: env.VITE_APP_TIENDA_TELEFONO || '',
  pie: env.VITE_APP_TIENDA_PIE || '¡Gracias por su compra!'
}
