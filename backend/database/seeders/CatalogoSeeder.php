<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Color;
use App\Models\Talla;
use Illuminate\Database\Seeder;

/**
 * Catálogo base de una tienda de ropa infantil, listo para usar en
 * producción: tallas (bebé, niño, juvenil), colores y categorías.
 *
 * Respeta lo que ya existe: compara sin mayúsculas ("BLANCO" = "Blanco") y
 * no toca orden, hexadecimal ni padre de lo que ya está cargado. Un color
 * cuyo hexadecimal ya usa otro color se saltea (el hex es único).
 */
class CatalogoSeeder extends Seeder
{
    /**
     * nombre => orden. Con huecos de 10 para intercalar tallas después sin
     * renumerar todo.
     */
    public const TALLAS = [
        // Bebé
        '0-3M' => 10, '3-6M' => 20, '6-9M' => 30, '9-12M' => 40, '12-18M' => 50, '18-24M' => 60,
        // Niño/a (numeración peruana)
        '2' => 100, '4' => 110, '6' => 120, '8' => 130, '10' => 140, '12' => 150, '14' => 160, '16' => 170,
        // Juvenil
        'XS' => 200, 'S' => 210, 'M' => 220, 'L' => 230, 'XL' => 240,
    ];

    public const COLORES = [
        'Blanco' => '#FFFFFF',
        'Negro' => '#000000',
        'Gris' => '#9CA3AF',
        'Gris melange' => '#BFC3C7',
        'Beige' => '#E8D9C0',
        'Crema' => '#FFF8E7',
        'Marrón' => '#7B4A2A',
        'Rojo' => '#DC2626',
        'Guinda' => '#7F1D1D',
        'Rosado' => '#F9A8D4',
        'Fucsia' => '#DB2777',
        'Lila' => '#C4B5FD',
        'Morado' => '#7C3AED',
        'Celeste' => '#7DD3FC',
        'Azul' => '#2563EB',
        'Azul marino' => '#1E3A8A',
        'Turquesa' => '#14B8A6',
        'Verde' => '#16A34A',
        'Verde agua' => '#99F6E4',
        'Verde militar' => '#4D5D2E',
        'Amarillo' => '#FACC15',
        'Mostaza' => '#CA8A04',
        'Naranja' => '#F97316',
        'Coral' => '#FB7185',
        'Jean' => '#4A6FA5',
        'Estampado' => '#A78BFA',
    ];

    /** raíz => subcategorías */
    public const CATEGORIAS = [
        'Bebés' => ['Bodies', 'Enterizos', 'Conjuntos', 'Pantalones', 'Baberos'],
        'Niños' => ['Polos', 'Camisas', 'Pantalones', 'Jeans', 'Shorts', 'Buzos', 'Casacas', 'Pijamas', 'Ropa interior'],
        'Niñas' => ['Polos', 'Blusas', 'Vestidos', 'Faldas', 'Pantalones', 'Jeans', 'Leggings', 'Shorts', 'Buzos', 'Casacas', 'Pijamas', 'Ropa interior'],
        'Escolar' => ['Buzos escolares', 'Polos escolares', 'Uniformes'],
        'Accesorios' => ['Medias', 'Gorros', 'Mochilas', 'Accesorios de cabello'],
    ];

    public function run(): void
    {
        foreach (self::TALLAS as $nombre => $orden) {
            if (! $this->existe(Talla::query(), (string) $nombre)) {
                Talla::create(['nombre' => (string) $nombre, 'orden' => $orden]);
            }
        }

        foreach (self::COLORES as $nombre => $hex) {
            if (! $this->existe(Color::query(), $nombre) && ! Color::where('hexadecimal', $hex)->exists()) {
                Color::create(['nombre' => $nombre, 'hexadecimal' => $hex]);
            }
        }

        foreach (self::CATEGORIAS as $raiz => $hijas) {
            $padre = $this->existe(Categoria::query()->whereNull('parent_id'), $raiz)
                ?? Categoria::create(['nombre' => $raiz, 'parent_id' => null]);

            foreach ($hijas as $hija) {
                if (! $this->existe(Categoria::query()->where('parent_id', $padre->id), $hija)) {
                    Categoria::create(['nombre' => $hija, 'parent_id' => $padre->id]);
                }
            }
        }
    }

    /** El registro con ese nombre, sin distinguir mayúsculas (o null). */
    private function existe($query, string $nombre)
    {
        return $query->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->first();
    }
}
