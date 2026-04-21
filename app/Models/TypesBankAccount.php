<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypesBankAccount extends Model
{
    use HasFactory;

    protected $table = 'types_bank_accounts';

    protected $fillable = [
        'name'
    ];
}
