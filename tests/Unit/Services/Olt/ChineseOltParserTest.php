<?php

namespace Tests\Unit\Services\Olt;

use App\Services\Olt\Support\ChineseOltParser;
use Tests\TestCase;

class ChineseOltParserTest extends TestCase
{
    public function test_parses_vsol_status_layout(): void
    {
        $output = "EPON0/1:1   online   78:d7:52:de:cf:7a\n"
            ."EPON0/1:2   offline  00:11:22:33:44:55\n"
            ."EPON0/2:1   online   aa:bb:cc:dd:ee:ff\n";

        $onus = ChineseOltParser::parseOnus($output);

        $this->assertCount(3, $onus);
        $this->assertSame('0/1/1', $onus[0]['onu_id']);
        $this->assertSame('online', $onus[0]['status']);
        $this->assertSame('78:d7:52:de:cf:7a', $onus[0]['mac']);
        $this->assertSame('offline', $onus[1]['status']);
        $this->assertSame(0, $onus[1]['slot']);
        $this->assertSame(1, $onus[1]['port']);
    }

    public function test_parses_vsol_basic_info_layout(): void
    {
        $output = "EPON0/1:1   HWTC      010H      78D752DECF7A\n"
            ."EPON0/1:2   HWTC      010H      48575443CC5522AA\n";

        $onus = ChineseOltParser::parseOnus($output);

        $this->assertCount(2, $onus);
        $this->assertSame('78D752DECF7A', $onus[0]['sn']);
        $this->assertSame('unknown', $onus[0]['status']);
    }

    public function test_parses_bdcom_pon_onu_information(): void
    {
        $output = "epon 0/1  1  78:d7:52:de:cf:7a  HWTCDE110A0001  Online   1GE\n"
            ."epon 0/1  2  a0:1b:2c:3d:4e:5f  HWTCDE110A0002  Offline  1GE\n";

        $onus = ChineseOltParser::parseOnus($output);

        $this->assertCount(2, $onus);
        $this->assertSame('0/1/1', $onus[0]['onu_id']);
        $this->assertSame('HWTCDE110A0001', $onus[0]['sn']);
        $this->assertSame('78:d7:52:de:cf:7a', $onus[0]['mac']);
        $this->assertSame('online', $onus[0]['status']);
        $this->assertSame('offline', $onus[1]['status']);
    }

    public function test_parses_optiway_onu_status(): void
    {
        $output = "3/4/1 00:0a:5a:12:46:59 54 00/021/02 00:40:08 2160 B01D001P005SP1 UP\n"
            ."3/4/2 00:0a:5a:12:46:60 55 00/021/03 00:40:08 2160 B01D001P005SP1 DOWN\n";

        $onus = ChineseOltParser::parseOnus($output);

        $this->assertCount(2, $onus);
        $this->assertSame('3/4/1', $onus[0]['onu_id']);
        $this->assertSame('online', $onus[0]['status']);
        $this->assertSame('offline', $onus[1]['status']);
    }

    public function test_parses_avies_bare_slot_port(): void
    {
        $output = "1/1  a2:3e:52:7a:11:bb  Offline\n"
            ."1/2  c4:5d:6e:7f:80:11  Online\n";

        $onus = ChineseOltParser::parseOnus($output);

        $this->assertCount(2, $onus);
        $this->assertSame('1/1/1', $onus[0]['onu_id']);
        $this->assertSame('offline', $onus[0]['status']);
        $this->assertSame('online', $onus[1]['status']);
    }

    public function test_parses_cortina_online_only_list(): void
    {
        $output = "onu-1 e0:67:b3:00:00:04 2.0 30 6m\n"
            ."onu-5 e0:67:b3:00:00:08 2.0 45 8m\n";

        $onus = ChineseOltParser::parseOnus($output);

        $this->assertCount(2, $onus);
        $this->assertSame('0/0/1', $onus[0]['onu_id']);
        $this->assertSame('online', $onus[0]['status']);
    }

    public function test_normalizes_unknown_status_words(): void
    {
        $this->assertSame('online', ChineseOltParser::normalizeStatus('Online'));
        $this->assertSame('online', ChineseOltParser::normalizeStatus('up'));
        $this->assertSame('offline', ChineseOltParser::normalizeStatus('LOS'));
        $this->assertSame('offline', ChineseOltParser::normalizeStatus('Down'));
        $this->assertSame('unknown', ChineseOltParser::normalizeStatus('010H'));
        $this->assertSame('unknown', ChineseOltParser::normalizeStatus(''));
    }

    public function test_parses_pon_totals(): void
    {
        $output = "PON       Total ONUs    Online ONUs\n"
            ."EPON0/1   43            29\n"
            ."EPON0/2   12            8\n";

        $totals = ChineseOltParser::parsePonTotals($output);

        $this->assertCount(2, $totals);
        $this->assertSame(['slot' => 0, 'port' => 1, 'total' => 43, 'online' => 29], $totals[0]);
        $this->assertSame(12, $totals[1]['total']);
        $this->assertSame(8, $totals[1]['online']);
    }
}
