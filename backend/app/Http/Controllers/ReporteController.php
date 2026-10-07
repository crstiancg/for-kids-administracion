<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Estadisticas;
use App\Support\Periodo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reportes para analizar con calma (el dashboard es el "cómo vamos hoy").
 * Exige `reportes.index`; lo que muestra COSTOS (valor del inventario,
 * ganancia) exige además `inventario.index`, igual que en el resto del
 * sistema.
 */
class ReporteController extends Controller
{
    public function __construct(private Estadisticas $estadisticas) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $periodo = Periodo::desdeRequest($request);
        $e = $this->estadisticas;

        $reporte = [
            'periodo' => $periodo->toArray(),
            'resumen' => [
                ...$e->resumenVentas($periodo),
                'anterior' => $e->resumenVentas($periodo->anterior())['total'],
            ],
            'por_canal' => $e->porCanal($periodo),
            'por_categoria' => $e->porCategoria($periodo),
            'por_cajero' => $e->porCajero($periodo),
            'por_metodo' => $e->porMetodo($periodo),
            'mejores_clientes' => $e->mejoresClientes($periodo),
            'ofertas_activas' => $e->ofertasActivas(),
        ];

        if ($usuario->can('inventario.index')) {
            $reporte['ganancia'] = $e->ganancia($periodo);
            $reporte['valor_inventario'] = $e->valorInventario();
        }

        return response()->json($reporte);
    }
}
