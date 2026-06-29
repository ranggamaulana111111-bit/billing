<?php

namespace App\Services\Olt\Factory;

use App\Models\Olt;
use App\Models\Setting;
use App\Services\Olt\Contracts\OltConnector;
use App\Services\Olt\Drivers\CDataConnector;
use App\Services\Olt\Drivers\FiberHomeConnector;
use App\Services\Olt\Drivers\HuaweiConnector;
use App\Services\Olt\Drivers\JumpHostConnector;
use App\Services\Olt\Drivers\MikrotikSshProxyConnector;
use App\Services\Olt\Drivers\ZteConnector;
use InvalidArgumentException;

class OltConnectorFactory
{
    public static function make(string $brand, ?Olt $olt = null): OltConnector
    {
        $brand = strtolower($brand);

        if ($olt && $olt->hasJumpHost()) {
            $mikrotikHost = Setting::get('mikrotik_host');

            if ($mikrotikHost && $olt->jump_host === $mikrotikHost) {
                return new MikrotikSshProxyConnector($brand);
            }

            $driver = self::createDriver($brand);

            return new JumpHostConnector(
                $driver,
                $olt->jump_host,
                $olt->jump_port,
                $olt->jump_username,
                $olt->jump_password,
            );
        }

        return self::createDriver($brand);
    }

    public static function makeRaw(string $brand): OltConnector
    {
        return self::createDriver(strtolower($brand));
    }

    private static function createDriver(string $brand): OltConnector
    {
        return match ($brand) {
            'huawei' => new HuaweiConnector,
            'zte' => new ZteConnector,
            'fiberhome' => new FiberHomeConnector,
            'cdata' => new CDataConnector,
            default => throw new InvalidArgumentException("Unsupported OLT brand: {$brand}"),
        };
    }
}
