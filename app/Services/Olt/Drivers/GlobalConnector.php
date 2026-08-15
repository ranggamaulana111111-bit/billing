<?php

namespace App\Services\Olt\Drivers;

/**
 * Global / Globtel OLT (BDCom-style CLI, EPON & GPON).
 *
 * CLI (config mode): show epon onu-information (or gpon variant),
 * show onu status all, show pon baisc-info (per-PON totals).
 */
class GlobalConnector extends ChineseOltConnector
{
    protected string $brandLabel = 'Global';

    protected array $statusCommands = [
        'show epon onu-information',
        'show gpon onu-information',
        'show onu status all',
        'show onu info all',
        'show onu baisc-info all',
        'show onu-status',
    ];

    protected array $totalsCommands = [
        'show pon baisc-info',
        'show pon basic-info',
        'show pon-info',
    ];
}
