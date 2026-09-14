<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-low-stock-alerts')]
#[Description('Command description')]
class SendLowStockAlerts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
    }
}
