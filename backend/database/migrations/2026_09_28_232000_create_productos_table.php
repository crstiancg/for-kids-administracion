<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->text('descripcion')->nullable();
            // Precio base: lo usan las variantes que no tienen precio propio.
            $table->decimal('precio', 10, 2);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('variantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('talla_id')->constrained('tallas')->restrictOnDelete();
            $table->foreignId('color_id')->constrained('colores')->restrictOnDelete();
            $table->string('sku', 40)->unique();
            // null = usa el precio base del producto.
            $table->decimal('precio', 10, 2)->nullable();
            // No se edita a mano: lo van a mover los movimientos de inventario.
            $table->integer('stock')->default(0);
            $table->timestamps();

            $table->unique(['producto_id', 'talla_id', 'color_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variantes');
        Schema::dropIfExists('productos');
    }
};
