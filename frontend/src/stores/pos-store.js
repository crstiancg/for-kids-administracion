import { defineStore } from 'pinia'

/**
 * Estado del punto de venta: el carrito en curso, sus pagos y las ventas en
 * espera. Se guarda en el navegador (una recarga o una pestaña cerrada por
 * error no pierde la venta). Es sólo un borrador: nada de esto es real hasta
 * que el backend registra la venta.
 */
const CLAVE = 'pos.borrador.v1'
const MAX_ESPERA = 5

let ultimoUid = 0
function nuevoPago (metodo = 'efectivo', monto = '0.00') {
  return { uid: ++ultimoUid, metodo, monto, recibido: '', referencia: '', montoManual: false }
}

function redondear (n) {
  return Math.round(n * 100) / 100
}

function numero (valor) {
  const n = Number(valor)
  return valor !== '' && valor !== null && Number.isFinite(n) ? n : 0
}

/**
 * Una línea del carrito a partir de una variante del catálogo (con su
 * producto) o de lo que devuelve el escáner (VarianteStockResource).
 */
export function lineaDesdeCatalogo (producto, variante) {
  return {
    variante_id: variante.id,
    producto_id: producto.id,
    sku: variante.sku,
    codigo_barras: variante.codigo_barras ?? null,
    nombre: producto.nombre,
    talla: variante.talla?.nombre ?? '',
    color: variante.color ?? null,
    stock: variante.stock,
    // El de hoy (con oferta) y el de lista, para mostrar el ahorro.
    precio_unitario: Number(variante.precio ?? producto.precio).toFixed(2),
    precio_lista: Number(variante.precio_lista ?? variante.precio ?? producto.precio).toFixed(2),
    miniatura_url: variante.miniatura_url ?? producto.miniatura_url ?? null
  }
}

export function lineaDesdeEscaner (variante) {
  return {
    variante_id: variante.id,
    producto_id: variante.producto?.id,
    sku: variante.sku,
    codigo_barras: variante.codigo_barras ?? null,
    nombre: variante.producto?.nombre ?? variante.sku,
    talla: variante.talla ?? '',
    color: variante.color ?? null,
    stock: variante.stock,
    precio_unitario: Number(variante.precio ?? 0).toFixed(2),
    precio_lista: Number(variante.precio_lista ?? variante.precio ?? 0).toFixed(2),
    miniatura_url: variante.miniatura_url ?? null
  }
}

export const usePosStore = defineStore('pos', {
  state: () => ({
    items: [],
    cliente: null,
    descuento: '',
    pagos: [nuevoPago()],
    // variante_id de la línea activa (para + / − / Supr desde el teclado).
    seleccionado: null,
    espera: []
  }),

  getters: {
    unidades: (s) => s.items.reduce((suma, i) => suma + i.cantidad, 0),
    subtotal: (s) => redondear(s.items.reduce((suma, i) => suma + i.cantidad * numero(i.precio_unitario), 0)),
    total () {
      return Math.max(0, redondear(this.subtotal - numero(this.descuento)))
    },
    asignado: (s) => redondear(s.pagos.reduce((suma, p) => suma + numero(p.monto), 0)),
    restante () {
      return redondear(this.total - this.asignado)
    },
    // Unidades de un producto en el carrito (sumando sus variantes): el ×N
    // de la tarjeta del catálogo.
    cantidadDeProducto: (s) => (productoId) =>
      s.items.filter((i) => i.producto_id === productoId).reduce((suma, i) => suma + i.cantidad, 0),
    cantidadDeVariante: (s) => (varianteId) => s.items.find((i) => i.variante_id === varianteId)?.cantidad ?? 0
  },

  actions: {
    /**
     * Agrega una unidad. Lo que ya está suma uno. Nunca más que el stock
     * (el backend lo valida igual al cobrar).
     *
     * @returns {'ok'|'sin-stock'}
     */
    agregar (linea) {
      const existente = this.items.find((i) => i.variante_id === linea.variante_id)
      const enCarrito = existente?.cantidad ?? 0

      if (enCarrito + 1 > linea.stock) return 'sin-stock'

      if (existente) {
        existente.cantidad++
        existente.stock = linea.stock
      } else {
        this.items.push({ ...linea, cantidad: 1 })
      }
      this.seleccionado = linea.variante_id
      return 'ok'
    },

    /** @returns {'ok'|'sin-stock'} */
    cambiarCantidad (varianteId, delta) {
      const item = this.items.find((i) => i.variante_id === varianteId)
      if (!item) return 'ok'

      const nueva = item.cantidad + delta
      if (nueva > item.stock) return 'sin-stock'
      if (nueva <= 0) {
        this.quitar(varianteId)
      } else {
        item.cantidad = nueva
      }
      return 'ok'
    },

    quitar (varianteId) {
      const i = this.items.findIndex((item) => item.variante_id === varianteId)
      if (i === -1) return
      this.items.splice(i, 1)
      // La selección pasa a la línea de al lado (seguir con el teclado).
      this.seleccionado = this.items[Math.min(i, this.items.length - 1)]?.variante_id ?? null
    },

    // Con un solo pago que no se tocó, el monto sigue al total.
    sincronizarPagoUnico () {
      if (this.pagos.length === 1 && !this.pagos[0].montoManual) {
        this.pagos[0].monto = this.total.toFixed(2)
      }
    },

    agregarPago () {
      this.pagos[0].montoManual = true
      this.pagos.push(nuevoPago('yape', this.restante > 0 ? this.restante.toFixed(2) : ''))
    },

    quitarPago (j) {
      this.pagos.splice(j, 1)
    },

    limpiar () {
      this.items = []
      this.cliente = null
      this.descuento = ''
      this.pagos = [nuevoPago()]
      this.seleccionado = null
    },

    // ── Ventas en espera ──
    // "Voy a buscar otra talla": se aparca el carrito y se atiende a otro.
    aparcar () {
      if (!this.items.length || this.espera.length >= MAX_ESPERA) return false
      this.espera.push({
        id: Date.now(),
        fecha: new Date().toISOString(),
        items: this.items,
        cliente: this.cliente,
        descuento: this.descuento
      })
      this.limpiar()
      return true
    },

    // Si hay una venta en curso, se aparca antes (intercambio).
    retomar (id) {
      const i = this.espera.findIndex((e) => e.id === id)
      if (i === -1) return
      const [venta] = this.espera.splice(i, 1)
      if (this.items.length) this.aparcar()
      this.items = venta.items
      this.cliente = venta.cliente
      this.descuento = venta.descuento
      this.pagos = [nuevoPago()]
      this.seleccionado = venta.items[0]?.variante_id ?? null
      this.sincronizarPagoUnico()
    },

    descartarEspera (id) {
      this.espera = this.espera.filter((e) => e.id !== id)
    },

    // ── Persistencia (borrador en este navegador) ──
    hidratar () {
      try {
        const guardado = JSON.parse(localStorage.getItem(CLAVE) ?? 'null')
        if (!guardado) return
        this.items = guardado.items ?? []
        this.cliente = guardado.cliente ?? null
        this.descuento = guardado.descuento ?? ''
        this.espera = guardado.espera ?? []
        this.pagos = [nuevoPago()]
        this.sincronizarPagoUnico()
      } catch {
        // Borrador ilegible o sin storage: se arranca limpio.
      }
    },

    persistir () {
      try {
        localStorage.setItem(CLAVE, JSON.stringify({
          items: this.items, cliente: this.cliente, descuento: this.descuento, espera: this.espera
        }))
      } catch {
        // Sin storage (modo privado, cuota): la venta sigue, sólo no se recuerda.
      }
    }
  }
})
