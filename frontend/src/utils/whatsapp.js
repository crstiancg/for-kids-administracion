import { formatearPrecio } from '@/utils/moneda'

/**
 * Ticket por WhatsApp sin API: un link wa.me con el texto ya armado. Abre
 * WhatsApp (web o app) con el mensaje listo; el cajero sólo toca "Enviar".
 */

// Un celular peruano (9 dígitos que empiezan con 9) sin código de país
// recibe el 51. Otro formato se usa tal cual: mejor que inventar.
export function normalizarTelefono (telefono) {
  const digitos = String(telefono ?? '').replace(/\D/g, '')
  if (!digitos) return ''
  return /^9\d{8}$/.test(digitos) ? `51${digitos}` : digitos
}

export function textoTicket (pedido, tienda = 'FOR KIDS') {
  const lineas = [
    `*${tienda}* · Ticket ${pedido.codigo}`,
    ''
  ]

  for (const item of pedido.items ?? []) {
    const v = item.variante ?? {}
    const nombre = [v.producto?.nombre, v.talla ? `T.${v.talla}` : null, v.color?.nombre].filter(Boolean).join(' ')
    lineas.push(`${item.cantidad} x ${nombre} — ${formatearPrecio(item.subtotal)}`)
  }

  lineas.push('')
  if (Number(pedido.descuento)) lineas.push(`Descuento: -${formatearPrecio(pedido.descuento)}`)
  lineas.push(`*Total: ${formatearPrecio(pedido.total)}*`)

  const metodos = [...new Set((pedido.pagos ?? []).filter((p) => Number(p.monto) > 0).map((p) => p.metodo_label))]
  if (metodos.length) lineas.push(`Pagado con: ${metodos.join(' + ')}`)

  lineas.push('', '¡Gracias por tu compra! 💛')
  return lineas.join('\n')
}

// Sin teléfono, wa.me abre WhatsApp para elegir el contacto a mano.
export function linkWhatsApp (telefono, texto) {
  const numero = normalizarTelefono(telefono)
  return `https://wa.me/${numero}?text=${encodeURIComponent(texto)}`
}
