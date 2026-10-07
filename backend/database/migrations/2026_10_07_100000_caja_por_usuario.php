<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caja POR USUARIO: cada cajero tiene su cajón, su apertura y su arqueo.
 * `abierta_por` pasa a ser el dueño de la caja, y la base sigue garantizando
 * la regla: como mucho UNA abierta por usuario (dos "Abrir caja" simultáneos
 * del mismo cajero no ganan los dos; NULL no choca con NULL al cerrarse).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cajas', function (Blueprint $table) {
            $table->dropUnique(['abierta']);
            $table->unique(['abierta_por', 'abierta']);
        });
    }

    public function down(): void
    {
        Schema::table('cajas', function (Blueprint $table) {
            $table->dropUnique(['abierta_por', 'abierta']);
            $table->unique('abierta');
        });
    }
};
