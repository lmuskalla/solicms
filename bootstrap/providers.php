<?php

use App\Providers\AppServiceProvider;
use App\Providers\TenancyServiceProvider;
use App\Providers\ThemeServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    ThemeServiceProvider::class,
];
