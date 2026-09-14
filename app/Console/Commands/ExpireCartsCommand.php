<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:expire-carts-command')]
#[Description('Command description')]
class ExpireCartsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
    }
}
