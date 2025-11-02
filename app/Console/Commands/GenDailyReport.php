<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Helpers\ReportHelper;

class GenDailyReport extends Command
{
    protected $signature = 'report:daily';
    protected $description = '生成或更新每日买家与代理日报表';

    public function handle()
    {
		ReportHelper::generateDailyReport();
    }
}
