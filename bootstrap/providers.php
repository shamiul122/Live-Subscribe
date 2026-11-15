<?php

use App\Providers\AppServiceProvider;
use App\Providers\BillingServiceProvider;
use App\Providers\CampaignServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TemplateServiceProvider;

return [
    AppServiceProvider::class,
    BillingServiceProvider::class,
    CampaignServiceProvider::class,
    TemplateServiceProvider::class,
    FortifyServiceProvider::class,
];
