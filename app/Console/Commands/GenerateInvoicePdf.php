<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:generate-invoice-pdf')]
#[Description('Command description')]
class GenerateInvoicePdf extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
    }
}
