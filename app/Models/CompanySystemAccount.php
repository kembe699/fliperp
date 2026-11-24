<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One company's account for one automatic-posting function (see SystemAccountService). */
class CompanySystemAccount extends Model
{
    protected $fillable = ['company_id', 'function', 'account_id'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
