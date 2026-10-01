<?php

namespace App\Http\Requests;

use App\Models\Archivo;
use App\Models\Producto;
use App\Models\Variante;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crea y actualiza un producto junto con sus variantes (talla × color).
 * Payload anidado en `producto`; las variantes en `producto.variantes`.
 *
 * El stock NO viene en el payload: sólo lo mueve el inventario.
 *
 * Viaja como multipart cuando hay fotos nuevas (el update, como POST con
 * _method=PUT: PHP no parsea multipart en un PUT). Fotos del producto en
 * `producto.archivos` y de cada variante en `producto.variantes.N.archivos`;
 * cada ítem es `{id}` (una foto ya guardada) o `{archivo}` (una nueva).
 */
class StoreProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $producto = $this->input('producto');
        if (! is_array($producto)) {
            return;
        }

        if (is_string($producto['nombre'] ?? null)) {
            $producto['nombre'] = trim($producto['nombre']);
        }

        if (($producto['descripcion'] ?? null) === '') {
            $producto['descripcion'] = null;
        }

        // En multipart un booleano llega como texto.
        if (in_array($producto['activo'] ?? null, ['true', 'false'], true)) {
            $producto['activo'] = $producto['activo'] === 'true';
        }

        if (is_array($producto['variantes'] ?? null)) {
            $producto['variantes'] = array_map(function ($variante) {
                if (! is_array($variante)) {
                    return $variante;
                }
                if (is_string($variante['sku'] ?? null)) {
                    $variante['sku'] = mb_strtoupper(trim($variante['sku']));
                }
                // Precio vacío = usa el precio base del producto.
                if (($variante['precio'] ?? null) === '') {
                    $variante['precio'] = null;
                }
                // Una medida vacía es "sin medida", no un error.
                if (is_array($variante['medidas'] ?? null)) {
                    $variante['medidas'] = array_filter(
                        $variante['medidas'],
                        fn ($valor) => $valor !== null && $valor !== '',
                    );
                }

                return $variante;
            }, $producto['variantes']);
        }

        $this->merge(['producto' => $producto]);
    }

    /**
     * Las reglas de cada variante se arman por índice: el unique del SKU
     * tiene que ignorar el id de ESA fila, no uno global.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $producto = $this->route('producto');

        $rules = [
            'producto.nombre' => ['required', 'string', 'max:120', $this->nombreUnico($producto)],
            'producto.categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'producto.descripcion' => ['nullable', 'string', 'max:1000'],
            'producto.precio' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'producto.activo' => ['required', 'boolean'],
            'producto.variantes' => ['required', 'array', 'min:1', $this->noQuitaVariantesConStock($producto)],
            // Stock inicial de las variantes nuevas: el costo general de la
            // compra (ajustable por variante) y la factura o guía.
            'producto.costo_compra' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'producto.referencia_compra' => ['nullable', 'string', 'max:60'],
            ...$this->reglasArchivos('producto.archivos'),
        ];

        foreach (array_keys((array) $this->input('producto.variantes', [])) as $i) {
            $rules["producto.variantes.{$i}.id"] = [
                'nullable', 'integer',
                // Sólo se editan variantes de ESTE producto (al crear, ninguna).
                Rule::exists('variantes', 'id')->where('producto_id', $producto?->getKey() ?? 0),
            ];
            $rules["producto.variantes.{$i}.talla_id"] = ['required', 'integer', 'exists:tallas,id'];
            $rules["producto.variantes.{$i}.color_id"] = [
                'required', 'integer', 'exists:colores,id',
                $this->combinacionUnica($i),
            ];
            $rules["producto.variantes.{$i}.sku"] = [
                'required', 'string', 'max:40', 'regex:/^[A-Z0-9-]+$/',
                $this->skuUnicoEnElProducto($i),
                // Contra OTROS productos: dentro de éste ya lo cubre la regla
                // anterior, y así se pueden intercambiar SKUs entre variantes
                // (o reusar el de una que se quita) en el mismo guardado.
                Rule::unique('variantes', 'sku')->where(fn ($q) => $q->where('producto_id', '!=', $producto?->getKey() ?? 0)),
            ];
            $rules["producto.variantes.{$i}.precio"] = ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'];
            $rules["producto.variantes.{$i}.stock_inicial"] = ['nullable', 'integer', 'min:0', 'max:100000', $this->stockInicialValido($i)];
            $rules["producto.variantes.{$i}.costo_unitario"] = ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'];
            $rules["producto.variantes.{$i}.medidas"] = ['nullable', 'array', 'max:12', $this->nombresDeMedidas()];
            $rules["producto.variantes.{$i}.medidas.*"] = ['numeric', 'min:0', 'max:999.9', 'decimal:0,1'];
            $rules = [...$rules, ...$this->reglasArchivos("producto.variantes.{$i}.archivos")];
        }

        return $rules;
    }

    public const MAX_ARCHIVOS = 6;

    /** @var int[]|null */
    private ?array $archivosPermitidos = null;

    /**
     * Por índice, igual que las variantes: cada ítem es una foto ya guardada
     * (`id`) o una nueva (`archivo`).
     *
     * @return array<string, mixed>
     */
    private function reglasArchivos(string $ruta): array
    {
        $rules = [$ruta => ['nullable', 'array', 'max:'.self::MAX_ARCHIVOS]];

        // all() y no input(): input() no trae los archivos subidos, y un ítem
        // que es sólo {archivo} se quedaría sin reglas (y sin validar).
        foreach (array_keys((array) data_get($this->all(), $ruta, [])) as $j) {
            $rules["{$ruta}.{$j}.id"] = ['nullable', 'integer', $this->archivoDelProducto()];
            $rules["{$ruta}.{$j}.archivo"] = [
                "required_without:{$ruta}.{$j}.id",
                'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096',
            ];
        }

        return $rules;
    }

    /**
     * Una foto existente se puede reusar en cualquier parte del MISMO
     * producto (así se copia a las otras tallas del mismo color), nunca una
     * de otro producto.
     */
    private function archivoDelProducto(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $this->archivosPermitidos ??= $this->idsDeArchivosDelProducto();

            if (! in_array((int) $value, $this->archivosPermitidos, true)) {
                $fail('La foto no pertenece a este producto.');
            }
        };
    }

    /**
     * @return int[]
     */
    private function idsDeArchivosDelProducto(): array
    {
        $producto = $this->route('producto');
        if (! $producto) {
            return [];
        }

        return Archivo::query()
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q
                    ->where('archivable_type', $producto->getMorphClass())
                    ->where('archivable_id', $producto->getKey()))
                ->orWhere(fn ($q) => $q
                    ->where('archivable_type', (new Variante)->getMorphClass())
                    ->whereIn('archivable_id', $producto->variantes()->select('id'))))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Sólo las variantes nuevas reciben stock desde acá (las existentes se
     * mueven en Inventario), y sin costo no entra: sin costo no hay forma de
     * calcular la ganancia de esas unidades.
     */
    private function stockInicialValido(int|string $i): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($i) {
            if ((int) $value <= 0) {
                return;
            }
            if ($this->input("producto.variantes.{$i}.id")) {
                $fail('El stock de una variante que ya existe se carga desde Inventario.');

                return;
            }

            $costo = $this->input("producto.variantes.{$i}.costo_unitario") ?? $this->input('producto.costo_compra');
            if ($costo === null || $costo === '') {
                $fail('Falta el costo de compra de estas unidades.');
            }
        };
    }

    private function nombresDeMedidas(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            foreach (array_keys((array) $value) as $nombre) {
                if (! is_string($nombre) || trim($nombre) === '' || mb_strlen($nombre) > 30) {
                    $fail('Cada medida necesita un nombre de hasta 30 caracteres.');

                    return;
                }
            }
        };
    }

    private function nombreUnico(?Producto $ignorar): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignorar) {
            $existe = Producto::query()
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower((string) $value)])
                ->when($ignorar, fn ($q) => $q->whereKeyNot($ignorar->getKey()))
                ->exists();

            if ($existe) {
                $fail('Ya existe un producto con ese nombre.');
            }
        };
    }

    /**
     * La misma talla y color dos veces en el mismo producto es la misma
     * variante. El error va en la fila repetida, no en la primera.
     */
    private function combinacionUnica(int|string $indice): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($indice) {
            $variantes = (array) $this->input('producto.variantes', []);
            $talla = $variantes[$indice]['talla_id'] ?? null;

            foreach ($variantes as $i => $otra) {
                if ($i === $indice) {
                    return;
                }
                if (($otra['talla_id'] ?? null) == $talla && ($otra['color_id'] ?? null) == $value) {
                    $fail('Esta talla y color ya están en otra variante.');

                    return;
                }
            }
        };
    }

    /**
     * `distinct` no sirve acá: sólo compara con reglas comodín
     * (`variantes.*.sku`) y éstas van por índice. Mismo criterio que
     * combinacionUnica: el error queda en la fila repetida.
     */
    private function skuUnicoEnElProducto(int|string $indice): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($indice) {
            foreach ((array) $this->input('producto.variantes', []) as $i => $otra) {
                if ($i === $indice) {
                    return;
                }
                if (($otra['sku'] ?? null) === $value) {
                    $fail('SKU repetido en este producto.');

                    return;
                }
            }
        };
    }

    /**
     * Quitar una variante que tiene stock haría desaparecer mercadería, y una
     * con historial de inventario dejaría movimientos sin su variante.
     */
    private function noQuitaVariantesConStock(?Producto $producto): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($producto) {
            if (! $producto) {
                return;
            }

            $conservadas = collect((array) $value)->pluck('id')->filter()->map(fn ($id) => (int) $id);

            $bloqueadas = $producto->variantes()
                ->whereNotIn('id', $conservadas)
                ->where(fn ($q) => $q->where('stock', '!=', 0)->orWhereHas('movimientos'))
                ->pluck('sku');

            if ($bloqueadas->isNotEmpty()) {
                $fail('No se pueden quitar variantes con stock o con historial de inventario: '.$bloqueadas->implode(', ').'.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'producto.variantes.required' => 'Agregá al menos una variante (talla y color).',
            'producto.variantes.min' => 'Agregá al menos una variante (talla y color).',
            'producto.variantes.*.sku.regex' => 'El SKU sólo puede tener letras, números y guiones.',
            'producto.variantes.*.sku.unique' => 'Ese SKU ya lo usa otra variante.',
            'producto.archivos.max' => 'Máximo '.self::MAX_ARCHIVOS.' fotos.',
            'producto.variantes.*.archivos.max' => 'Máximo '.self::MAX_ARCHIVOS.' fotos por variante.',
            'producto.archivos.*.archivo.max' => 'Cada foto puede pesar hasta 4 MB.',
            'producto.variantes.*.archivos.*.archivo.max' => 'Cada foto puede pesar hasta 4 MB.',
            'producto.archivos.*.archivo.mimes' => 'Sólo fotos JPG, PNG o WEBP.',
            'producto.variantes.*.archivos.*.archivo.mimes' => 'Sólo fotos JPG, PNG o WEBP.',
            'producto.variantes.*.medidas.*.numeric' => 'Las medidas van en cm, sólo números.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'producto.nombre' => 'nombre',
            'producto.categoria_id' => 'categoría',
            'producto.descripcion' => 'descripción',
            'producto.precio' => 'precio',
            'producto.activo' => 'activo',
            'producto.variantes.*.talla_id' => 'talla',
            'producto.variantes.*.color_id' => 'color',
            'producto.variantes.*.sku' => 'SKU',
            'producto.variantes.*.precio' => 'precio',
            'producto.variantes.*.stock_inicial' => 'stock inicial',
            'producto.variantes.*.costo_unitario' => 'costo',
            'producto.costo_compra' => 'costo de compra',
            'producto.referencia_compra' => 'factura o guía',
            'producto.variantes.*.medidas.*' => 'medida',
            'producto.archivos.*.archivo' => 'foto',
            'producto.variantes.*.archivos.*.archivo' => 'foto',
        ];
    }
}
