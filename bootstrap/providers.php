<?php

use App\Providers\AppServiceProvider;
use App\Providers\BillingServiceProvider;
use App\Providers\CampaignServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    BillingServiceProvider::class,
    CampaignServiceProvider::class,
    FortifyServiceProvider::class,
];
