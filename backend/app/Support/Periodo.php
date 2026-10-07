<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Un rango de días del NEGOCIO (no UTC): [desde, hasta) más el período
 * anterior de la misma duración, para comparar. Lo comparten el dashboard y
 * los reportes, así "semana" significa lo mismo en las dos pantallas.
 *
 * ?periodo=hoy|semana|mes|rango (&desde=YYYY-MM-DD&hasta=YYYY-MM-DD, inclusivo)
 */
final class Periodo
{
    public const MAX_DIAS = 366;

    private function __construct(
        public readonly string $tipo,
        public readonly CarbonImmutable $desde,
        public readonly CarbonImmutable $hasta,
    ) {}

    public static function desdeRequest(Request $request): self
    {
        $zona = config('app.zona_negocio');
        $hoy = CarbonImmutable::now($zona)->startOfDay();
        $manana = $hoy->addDay();

        return match ($request->input('periodo', 'hoy')) {
            // Semana = los últimos 7 días (hoy incluido), no "desde el lunes":
            // un lunes a la mañana la semana calendario estaría vacía.
            'semana' => new self('semana', $hoy->subDays(6), $manana),
            'mes' => new self('mes', $hoy->startOfMonth(), $manana),
            'rango' => self::rango($request, $zona, $hoy),
            default => new self('hoy', $hoy, $manana),
        };
    }

    /** Los últimos N días, hoy incluido. */
    public static function ultimos(int $dias): self
    {
        $hoy = CarbonImmutable::now(config('app.zona_negocio'))->startOfDay();

        return new self('rango', $hoy->subDays($dias - 1), $hoy->addDay());
    }

    public static function mesActual(): self
    {
        $hoy = CarbonImmutable::now(config('app.zona_negocio'))->startOfDay();

        return new self('mes', $hoy->startOfMonth(), $hoy->addDay());
    }

    private static function rango(Request $request, string $zona, CarbonImmutable $hoy): self
    {
        $leer = fn (string $campo, CarbonImmutable $porDefecto) => rescue(
            fn () => CarbonImmutable::createFromFormat('Y-m-d', (string) $request->input($campo), $zona)->startOfDay(),
            $porDefecto,
            report: false,
        );

        $desde = $leer('desde', $hoy->subDays(29));
        $hasta = $leer('hasta', $hoy);
        if ($hasta->lt($desde)) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        // Tope: un rango de años arrastraría toda la base a la memoria.
        if ($desde->diffInDays($hasta) >= self::MAX_DIAS) {
            $desde = $hasta->subDays(self::MAX_DIAS - 1);
        }

        return new self('rango', $desde, $hasta->addDay());
    }

    public function dias(): int
    {
        return (int) round($this->desde->diffInDays($this->hasta));
    }

    /** El período anterior de igual duración (para el "vs"). */
    public function anterior(): self
    {
        return new self($this->tipo, $this->desde->subDays($this->dias()), $this->desde);
    }

    /** Bordes en UTC, que es como se guardan los timestamps. */
    public function desdeUtc(): CarbonImmutable
    {
        return $this->desde->utc();
    }

    public function hastaUtc(): CarbonImmutable
    {
        return $this->hasta->utc();
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'tipo' => $this->tipo,
            'desde' => $this->desde->toDateString(),
            // Inclusivo para mostrar.
            'hasta' => $this->hasta->subDay()->toDateString(),
        ];
    }
}
