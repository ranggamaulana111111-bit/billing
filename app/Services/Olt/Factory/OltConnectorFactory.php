<?php

namespace App\Services\Olt\Factory;

use App\Services\Olt\Contracts\OltConnector;
use App\Services\Olt\Drivers\FiberHomeConnector;
use App\Services\Olt\Drivers\HuaweiConnector;
use App\Services\Olt\Drivers\ZteConnector;
use InvalidArgumentException;

class OltConnectorFactory
{
    public static function make(string $brand): OltConnector
    {
        return match (strtolower($brand)) {
            'huawei' => new HuaweiConnector,
            'zte' => new ZteConnector,
            'fiberhome' => new FiberHomeConnector,
            default => throw new InvalidArgumentException("Unsupported OLT brand: {$brand}"),
        };
    }
}
