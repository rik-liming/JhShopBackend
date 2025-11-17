<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Helpers\AdminMessageHelper;

class AdminMessageClear extends Command
{
    protected $signature = 'adminMessage:clear';
    protected $description = '清理已读无用信息';

    public function handle()
    {
      AdminMessageHelper::clearReadMessages();
    }
}
