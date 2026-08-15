<?php

namespace App\Services\Olt\Drivers;

/**
 * Hioso OLT (mini PON, many models share the VSOL/BDCom platform).
 *
 * CLI (config mode): show onu status all, show onu running config all,
 * show pon baisc-info.
 */
class HiosoConnector extends ChineseOltConnector
{
    protected string $brandLabel = 'Hioso';

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
