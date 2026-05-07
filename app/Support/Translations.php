<?php

namespace App\Support;

/**
 * Static translation maps for fixed vocabulary.
 * All strings that can change (product names, descriptions) use _en DB fields instead.
 * This class is never called at write-time — only during request reads.
 */
class Translations
{
    public static array $categories = [
        'Celulares'                   => 'Phones',
        'Tecnología'                  => 'Technology',
        'Artículos deportivos'        => 'Sports',
        'Hogar'                       => 'Home & Living',
        'Moda'                        => 'Fashion',
        'Herramientas'                => 'Tools',
        'Belleza'                     => 'Beauty',
        'Calzado'                     => 'Footwear',
        'Artículos de Lujo'           => 'Luxury',
        'Bebé'                        => 'Baby',
        'Automotriz'                  => 'Automotive',
        'Mascotas'                    => 'Pets',
        'Juguetes'                    => 'Toys',
        'Proteínas y Suplementos'     => 'Supplements',
        'Cámaras'                     => 'Cameras',
        'Gamers'                      => 'Gaming',
        'Computadoras'                => 'Computers',
        'Tablets'                     => 'Tablets',
        'Made in Colombia - Fashion'  => 'Made in Colombia - Fashion',
        'Made in Colombia - Joyeria'  => 'Made in Colombia - Jewelry',
        'Música'                      => 'Music',
        'Utiles Escolares'            => 'School Supplies',
    ];

    public static array $purchaseStatuses = [
        'Pendiente'            => 'Pending',
        'Completado'           => 'Completed',
        'Fallida'              => 'Failed',
        'Devolución'           => 'Return',
        'Pagada'               => 'Paid',
        'En Recepción'         => 'At Reception',
        'En Proceso de Envío'  => 'On the way',
    ];

    public static array $shipmentStatuses = [
        'Pedido realizado' => 'Order placed',
        'En proceso'       => 'Processing',
        'Enviado'          => 'On the way',
        'Entregado'        => 'Delivered',
    ];

    public static function category(string $name): string
    {
        return static::$categories[$name] ?? $name;
    }

    public static function purchaseStatus(string $name): string
    {
        return static::$purchaseStatuses[$name] ?? $name;
    }

    public static function shipmentStatus(string $name): string
    {
        return static::$shipmentStatuses[$name] ?? $name;
    }
}
