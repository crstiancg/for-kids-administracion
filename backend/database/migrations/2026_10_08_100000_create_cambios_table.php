<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cambios (de talla, de color…) y el saldo a favor de los clientes.
 *
 * Un cambio: lo que VUELVE de una venta entregada entra al inventario con su
 * costo original y su valor (lo que realmente se pagó) pasa a saldo a favor;
 * lo que se LLEVA es un pedido nuevo que se paga primero con ese saldo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cambios', function (Blueprint $table) {
            $table->id();
            // La venta de donde vuelve la prenda.
            $table->foreignId('pedido_id')->constrained('pedidos')->restrictOnDelete();
            // El pedido con lo que se lleva (null si sólo devolvió y quedó saldo).
            $table->foreignId('pedido_nuevo_id')->nullable()->constrained('pedidos')->restrictOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            // Lo que vale lo devuelto: lo PAGADO (descuento prorrateado).
            $table->decimal('valor_devuelto', 12, 2);
            $table->string('observacion', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('cambio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cambio_id')->constrained('cambios')->cascadeOnDelete();
            // La línea de la venta original: controla que no vuelva más de
            // lo que se vendió (sumando cambios anteriores).
            $table->foreignId('pedido_item_id')->constrained('pedido_items')->restrictOnDelete();
            $table->foreignId('variante_id')->constrained('variantes')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            // Por unidad, congelados: lo pagado y el costo de esa venta. Las
            // estadísticas los restan de ventas y ganancia del día del cambio.
            $table->decimal('valor_unitario', 10, 2);
            $table->decimal('costo_unitario', 12, 4)->nullable();
        });

        // Libro del saldo a favor: INMUTABLE, el saldo es la suma. +crédito
        // (un cambio), −uso (pagar con saldo).
        Schema::create('movimientos_saldo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->decimal('monto', 12, 2);
            $table->string('concepto', 200);
            $table->foreignId('cambio_id')->nullable()->constrained('cambios')->restrictOnDelete();
            $table->foreignId('pago_id')->nullable()->constrained('pagos')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['cliente_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_saldo');
        Schema::dropIfExists('cambio_items');
        Schema::dropIfExists('cambios');
    }
};
