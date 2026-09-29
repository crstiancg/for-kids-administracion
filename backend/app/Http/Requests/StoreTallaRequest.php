<?php

namespace App\Http\Requests;

use App\Models\Talla;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Crea y actualiza tallas. Payload anidado en `talla`.
 */
class StoreTallaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El nombre se guarda en mayúsculas: "xl" y "XL" son la misma talla y
     * así se ve igual en todas las pantallas y en los SKU.
     */
    protected function prepareForValidation(): void
    {
        $talla = $this->input('talla');
        if (! is_array($talla)) {
            return;
        }

        if (is_string($talla['nombre'] ?? null)) {
            $talla['nombre'] = mb_strtoupper(trim($talla['nombre']));
        }

        $this->merge(['talla' => $talla]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $talla = $this->route('talla');

        return [
            'talla.nombre' => ['required', 'string', 'max:20', $this->nombreUnico($talla)],
            'talla.orden' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    private function nombreUnico(?Talla $ignorar): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignorar) {
            $existe = Talla::query()
                ->where('nombre', (string) $value)
                ->when($ignorar, fn ($q) => $q->whereKeyNot($ignorar->getKey()))
                ->exists();

            if ($existe) {
                $fail('Ya existe una talla con ese nombre.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'talla.nombre' => 'nombre',
            'talla.orden' => 'orden',
        ];
    }
}
