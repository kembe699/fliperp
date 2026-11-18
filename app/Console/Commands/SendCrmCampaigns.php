<?php

namespace App\Console\Commands;

use App\Services\Crm\EmailCampaignService;
use Illuminate\Console\Command;

/** Sends due CRM email campaigns in small batches; scheduled every minute. */
class SendCrmCampaigns extends Command
{
    protected $signature = 'crm:send-campaigns {--limit=40 : Most emails to send in this run}';

    protected $description = 'Start due CRM email campaigns and send the next batch of emails';

    public function handle(EmailCampaignService $campaigns): int
    {
        $sent = $campaigns->processDue((int) $this->option('limit'));
        $this->info("Sent {$sent} campaign email(s).");

        return self::SUCCESS;
    }
}
