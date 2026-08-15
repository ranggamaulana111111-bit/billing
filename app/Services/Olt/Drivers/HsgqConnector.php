<?php

namespace App\Services\Olt\Drivers;

/**
 * HSGQ OLT (mini PON, switch-style CLI on some models).
 *
 * CLI: show onu list / show onu status / show epon onu-information.
 */
class HsgqConnector extends ChineseOltConnector
{
    protected string $brandLabel = 'HSGQ';

    protected array $statusCommands = [
        'show epon onu-information',
        'show onu status all',
        'show onu info all',
        'show onu list',
        'show onu-status',
    ];

    protected array $totalsCommands = [
        'show pon baisc-info',
        'show pon basic-info',
        'show pon-info',
    ];
}
