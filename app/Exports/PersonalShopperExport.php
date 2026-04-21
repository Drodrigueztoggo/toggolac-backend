<?php

namespace App\Exports;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\View\View;

class PersonalShopperExport implements FromView
{
    use Exportable;

    protected $users;

    public function __construct($users)
    {
        $this->users = $users;
    }

    public function collection()
    {
        return $this->users;
    }


    public function view(): View
    {

        return view('Export.personalShopper', [
            'data' => $this->users,
        ]);
    }
}