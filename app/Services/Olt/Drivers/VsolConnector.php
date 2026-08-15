<?php

namespace App\Services\Olt\Drivers;

/**
 * VSOL / BDCom mini-OLT (V2802, V2801, V2804, ...).
 *
 * CLI (config mode): show onu status all, show onu info all,
 * show pon baisc-info (per-PON totals).
 */
class VsolConnector extends ChineseOltConnector
{
    protected string $brandLabel = 'VSOL';

    protected array $statusCommands = [
        'show onu status all',
        'show onu info all',
        'show onu baisc-info all',
        'show onu basic-info all',
        'show onu-status',
    ];

    protected array $totalsCommands = [
        'show pon baisc-info',
        'show pon basic-info',
        'show pon-info',
    ];
}
