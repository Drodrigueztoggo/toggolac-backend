<?php

namespace App\Console\Commands;

use App\Http\Controllers\CurrencyController;
use Illuminate\Console\Command;

class UpdateCurrencies extends Command
{
  
    protected $signature = 'currencies:update';
    protected $description = 'Update currencies at midnight';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $controller = new CurrencyController();
        $controller->updateCurrencies();
    }
    
}
