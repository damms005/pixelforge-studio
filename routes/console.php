<?php

use Illuminate\Support\Facades\Schedule;

// Webhooks are not guaranteed; poll generations that have gone quiet.
Schedule::command('generations:sync')->everyMinute()->withoutOverlapping();
