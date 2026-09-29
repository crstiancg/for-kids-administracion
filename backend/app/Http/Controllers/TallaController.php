<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTallaRequest;
use App\Models\Talla;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TallaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Sin order_by explícito, en el orden de exhibición y no alfabético.
        if (! $request->filled('order_by')) {
            $request->merge(['order_by' => 'orden,nombre']);
        }

        return $this->generateViewSetList(
            $request,
            Talla::query(),
            [],
            ['nombre'],
            ['id', 'nombre', 'orden'],
        );
    }

    public function store(StoreTallaRequest $request): JsonResponse
    {
        return response()->json(Talla::create($request->validated('talla')), 201);
    }

    public function show(Talla $talla): JsonResponse
    {
        return response()->json($talla);
    }

    public function update(StoreTallaRequest $request, Talla $talla): JsonResponse
    {
        $talla->update($request->validated('talla'));

        return response()->json($talla);
    }

    public function destroy(Talla $talla): JsonResponse|Response
    {
        // La FK de variantes es restrict: sin este chequeo sería un 500.
        if ($talla->variantes()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: hay productos con esta talla.',
            ], 409);
        }

        $talla->delete();

        return response()->noContent();
    }
}
