<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', fn () => $this->comment('Keep shipping.'))
    ->purpose('Display an inspiring quote');
