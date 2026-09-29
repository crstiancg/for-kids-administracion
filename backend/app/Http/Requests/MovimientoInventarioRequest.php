<?php

namespace App\Http\Requests;

use App\Models\Variante;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de entradas, salidas y ajustes: un documento (`movimiento`) con varias
 * líneas (`movimiento.lineas`), una por variante.
 *
 * Lo que valida acá es la forma. El stock suficiente de una salida también
 * se chequea acá (para avisar mientras se tipea), pero la palabra final la
 * tiene App\Services\Inventario con la fila bloqueada: entre la validación y
 * el guardado otra persona pudo haber descontado.
 */
abstract class MovimientoInventarioRequest extends FormRequest
{
    public const MAX_LINEAS = 200;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas propias de cada línea según el tipo.
     *
     * @return array<string, mixed>
     */
    abstract protected function reglasDeLinea(int|string $i): array;

    /**
     * Reglas del encabezado además de referencia y observación.
     *
     * @return array<string, mixed>
     */
    protected function reglasDelDocumento(): array
    {
        return [];
    }

    protected function prepareForValidation(): void
    {
        $movimiento = $this->input('movimiento');
        if (! is_array($movimiento)) {
            return;
        }

        foreach (['referencia', 'observacion'] as $campo) {
            if (is_string($movimiento[$campo] ?? null)) {
                $movimiento[$campo] = trim($movimiento[$campo]) ?: null;
            }
        }

        $this->merge(['movimiento' => $movimiento]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'movimiento.referencia' => ['nullable', 'string', 'max:60'],
            'movimiento.observacion' => ['nullable', 'string', 'max:500'],
            'movimiento.lineas' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINEAS],
            ...$this->reglasDelDocumento(),
        ];

        foreach (array_keys((array) $this->input('movimiento.lineas', [])) as $i) {
            $rules["movimiento.lineas.{$i}.variante_id"] = [
                'required', 'integer', 'exists:variantes,id', $this->varianteUnica($i),
            ];
            $rules = [...$rules, ...$this->reglasDeLinea($i)];
        }

        return $rules;
    }

    /**
     * La misma variante dos veces en un documento es un error de carga (¿se
     * sumaban o era la misma línea?): se pide unificarla.
     */
    private function varianteUnica(int|string $indice): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($indice) {
            foreach ((array) $this->input('movimiento.lineas', []) as $i => $otra) {
                if ($i === $indice) {
                    return;
                }
                if (($otra['variante_id'] ?? null) == $value) {
                    $fail('Esta variante ya está en otra línea.');

                    return;
                }
            }
        };
    }

    /**
     * Stock de la variante de la línea (null si todavía no se eligió).
     */
    protected function stockDe(int|string $i): ?int
    {
        $id = $this->input("movimiento.lineas.{$i}.variante_id");

        return is_numeric($id) ? Variante::query()->whereKey($id)->value('stock') : null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'movimiento.lineas.required' => 'Agregá al menos una línea.',
            'movimiento.lineas.min' => 'Agregá al menos una línea.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'movimiento.referencia' => 'referencia',
            'movimiento.observacion' => 'observación',
            'movimiento.motivo' => 'motivo',
            'movimiento.lineas.*.variante_id' => 'variante',
            'movimiento.lineas.*.cantidad' => 'cantidad',
            'movimiento.lineas.*.costo_unitario' => 'costo unitario',
            'movimiento.lineas.*.stock_real' => 'stock contado',
        ];
    }
}
