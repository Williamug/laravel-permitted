<?php

namespace Williamug\Permitted\Commands;

use Illuminate\Console\Command;

class PermittedCommand extends Command
{
    public $signature = 'laravel-permitted';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
