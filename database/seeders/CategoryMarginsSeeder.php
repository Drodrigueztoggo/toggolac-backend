<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CategoryMargin;

class CategoryMarginsSeeder extends Seeder
{
    public function run(): void
    {
        $margins = [
            44 => 30, // Celulares
            45 => 28, // Tecnología
            46 => 35, // Artículos deportivos
            47 => 40, // Hogar
            48 => 45, // Moda
            49 => 38, // Herramientas
            50 => 40, // Belleza
            51 => 45, // Calzado
            52 => 50, // Artículos de Lujo
            53 => 40, // Bebé
            54 => 38, // Automotriz
            55 => 38, // Mascotas
            56 => 40, // Juguetes
            57 => 35, // Proteínas y Suplementos
            60 => 32, // Cámaras
            61 => 30, // Gamers
            63 => 28, // Computadoras
            64 => 28, // Tablets
            65 => 55, // Made in Colombia - Fashion
            66 => 60, // Made in Colombia - Joyería
            68 => 35, // Música
            69 => 42, // Útiles Escolares
        ];

        foreach ($margins as $categoryId => $margin) {
            CategoryMargin::updateOrCreate(
                ['category_id' => $categoryId],
                [
                    'min_margin_percent'                => $margin,
                    'price_increase_alert_threshold'    => 10.00,
                    'price_increase_unpublish_threshold'=> 30.00,
                ]
            );
        }
    }
}
