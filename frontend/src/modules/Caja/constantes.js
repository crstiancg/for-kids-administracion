// Mismas claves que Pago::METODOS del backend.
export const METODOS = [
  { value: 'efectivo', label: 'Efectivo', icon: 'payments' },
  { value: 'yape', label: 'Yape', icon: 'qr_code_2' },
  { value: 'plin', label: 'Plin', icon: 'qr_code_2' },
  { value: 'transferencia', label: 'Transferencia', icon: 'account_balance' },
  { value: 'tarjeta', label: 'Tarjeta', icon: 'credit_card' }
]

// Los que se verifican contra el celular o el banco: piden n° de operación
// (igual que CobrarPedidoRequest::CON_OPERACION).
export const CON_OPERACION = ['yape', 'plin', 'transferencia']
