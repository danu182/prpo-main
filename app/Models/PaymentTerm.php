<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTerm extends Model
{

    protected $table = 'payment_terms';

    protected $guarded = ['id'];
}
