<?php

namespace App\Exports;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\View\View;

class PurchasesExport implements FromView
{
    use Exportable;

    protected $purchase;

    public function __construct($purchase)
    {
        $this->purchase = $purchase;
    }

    public function collection()
    {
        return $this->purchase;
    }

   

    public function view(): View
    {

        return view('Export.purchases', [
            'purchases' => $this->purchase,
        ]);
    }
}