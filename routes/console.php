<?php

use App\Console\Commands\ImportHub;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ImportHub::class)->hourly();
