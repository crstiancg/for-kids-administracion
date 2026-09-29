/**
 * Estados: label, status del AppChip e ícono nacen juntos (mismo criterio que
 * Inventario), así nunca se desincronizan.
 */
export const ESTADOS = {
  pendiente: { label: 'Pendiente', status: 'warning' },
  confirmado: { label: 'Confirmado', status: 'info' },
  entregado: { label: 'Entregado', status: 'positive' },
  cancelado: { label: 'Cancelado', status: 'negative' }
}

// Mismas claves que Pedido::CANALES del backend.
export const CANALES = [
  { value: 'mostrador', label: 'Mostrador' },
  { value: 'whatsapp', label: 'WhatsApp' },
  { value: 'redes', label: 'Redes sociales' },
  { value: 'web', label: 'Web' }
]
