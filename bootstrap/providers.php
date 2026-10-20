<?php

declare(strict_types=1);

use App\Modules\Catalog\Providers\CatalogServiceProvider;
use App\Modules\IdentityAccess\Providers\FortifyServiceProvider;
use App\Modules\IdentityAccess\Providers\IdentityAccessServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    IdentityAccessServiceProvider::class,
    CatalogServiceProvider::class,
];
