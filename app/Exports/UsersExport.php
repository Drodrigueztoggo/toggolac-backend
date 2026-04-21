<?php

namespace App\Exports;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithMapping;

class UsersExport implements FromQuery, WithHeadings, WithMapping, WithChunkReading
{
    use Exportable;

    public function __construct(
        private ?string $startDate = null,
        private ?string $endDate = null
    ) {}

    // Streams query directly — never loads all rows into memory
    public function query()
    {
        return User::query()
            ->select([
                'id',
                'name',
                'email',
                'created_at',
                'country_id',
                'city_id',
                'birth_date',
            ])
            ->with('countryInfo:id,name', 'cityInfo:id,name')
            ->withCount([
                'orders as num_purchases' => fn($q) =>
                    $q->where('purchase_status_id', 2)
            ])
            ->withSum([
                'orders as total_amount' => fn($q) =>
                    $q->where('purchase_status_id', 2)
            ], 'purchase_order_details.price')
            ->where('role_id', 3)
            ->when($this->startDate, fn($q) =>
                $q->whereDate('created_at', '>=', $this->startDate)
            )
            ->when($this->endDate, fn($q) =>
                $q->whereDate('created_at', '<=', $this->endDate)
            )
            ->orderBy('created_at', 'desc');
    }

    // Column headers in your Excel file
    public function headings(): array
    {
        return [
            '#',
            'Nombre',
            'Email',
            'Fecha de Creación',
            'País',
            'Ciudad',
            'Edad',
            'Número de Compras',
            'Total de Compras',
        ];
    }

    // How each row is formatted — runs per row, not all at once
    public function map($user): array
    {
        static $count = 0;
        $count++;

        return [
            $count,
            $user->name,
            $user->email,
            Carbon::parse($user->created_at)->format('d/m/Y'),
            $user->countryInfo?->name ?? '—',
            $user->cityInfo?->name ?? '—',
            $user->birth_date ? Carbon::parse($user->birth_date)->age : '—',
            $user->num_purchases ?? 0,
            '$' . number_format($user->total_amount ?? 0, 2),
        ];
    }

    // Process 200 rows at a time — never runs out of memory
    public function chunkSize(): int
    {
        return 200;
    }
}
