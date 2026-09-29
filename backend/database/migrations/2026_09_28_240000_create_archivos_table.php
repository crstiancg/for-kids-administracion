<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Archivos polimórficos (patrón de sistema-botica): cualquier modelo con
     * morphMany(Archivo::class, 'archivable') puede tener adjuntos.
     */
    public function up(): void
    {
        Schema::create('archivos', function (Blueprint $table) {
            $table->id();
            $table->morphs('archivable');
            // Nombre con el que se subió, sólo para mostrar. En disco se
            // guarda con un nombre único (`ruta`): dos "foto.jpg" de productos
            // distintos no se pisan.
            $table->string('nombre');
            $table->string('ruta');
            $table->string('mime', 100);
            $table->unsignedInteger('tamano');
            // 0 = portada.
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::table('variantes', function (Blueprint $table) {
            // {"Largo": 52, "Pecho": 40} en cm. Dependen de la talla, pero se
            // guardan por variante: el form las carga por talla y las copia.
            $table->json('medidas')->nullable()->after('precio');
        });
    }

    public function down(): void
    {
        Schema::table('variantes', function (Blueprint $table) {
            $table->dropColumn('medidas');
        });

        Schema::dropIfExists('archivos');
    }
};
