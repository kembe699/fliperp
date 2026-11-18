<?php

namespace App\Models;

/** An address that asked to stop receiving this company's marketing emails. */
class CrmEmailUnsubscribe extends TenantModel
{
    protected $fillable = ['company_id', 'email', 'campaign_id'];
}
