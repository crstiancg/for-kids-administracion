<?php

namespace App\Http\Requests;

use App\Models\Pago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Forma del cambio. Las reglas de negocio (plazo, cantidades disponibles,
 * diferencia exacta) las valida App\Services\Cambios con la base bloqueada.
 */
class RegistrarCambioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // El saldo lo aplica el sistema solo: la diferencia se paga con plata.
        $metodos = array_values(array_diff(array_keys(Pago::METODOS), [Pago::SALDO]));

        return [
            'cambio.cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'cambio.observacion' => ['nullable', 'string', 'max:500'],
            'cambio.devueltos' => ['required', 'array', 'min:1'],
            'cambio.devueltos.*.pedido_item_id' => ['required', 'integer'],
            'cambio.devueltos.*.cantidad' => ['required', 'integer', 'min:1'],
            'cambio.nuevos' => ['present', 'array'],
            'cambio.nuevos.*.variante_id' => ['required', 'integer', 'exists:variantes,id'],
            'cambio.nuevos.*.cantidad' => ['required', 'integer', 'min:1'],
            'cambio.pagos' => ['present', 'array'],
            'cambio.pagos.*.metodo' => ['required', Rule::in($metodos)],
            'cambio.pagos.*.monto' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'cambio.pagos.*.recibido' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'cambio.pagos.*.referencia' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cambio.cliente_id' => 'cliente',
            'cambio.devueltos' => 'prendas que vuelven',
            'cambio.devueltos.*.cantidad' => 'cantidad',
            'cambio.nuevos.*.cantidad' => 'cantidad',
            'cambio.pagos.*.metodo' => 'método',
            'cambio.pagos.*.monto' => 'monto',
        ];
    }
}
