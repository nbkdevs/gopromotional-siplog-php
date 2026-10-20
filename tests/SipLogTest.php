<?php

declare(strict_types=1);

use GoPromotional\SipLog\SipLog;
use PHPUnit\Framework\TestCase;

final class SipLogTest extends TestCase
{
    public function testDailyGoal(): void
    {
        $this->assertSame(2300, SipLog::dailyGoal(70));
        $this->assertSame(3150, SipLog::dailyGoal(70, 30, true));
        $this->assertSame(3250, SipLog::dailyGoal(82.5, 45));
        $this->expectException(InvalidArgumentException::class);
        SipLog::dailyGoal(0);
    }

    public function testParseAmount(): void
    {
        foreach (['500ml' => 500, '0.5l' => 500, '1.5 L' => 1500, '33cl' => 330, '330' => 330, '16oz' => 473] as $in => $ml) {
            $this->assertSame($ml, SipLog::parseAmount((string) $in), (string) $in);
        }
        $this->expectExceptionMessage('Cannot read amount "a glass"');
        SipLog::parseAmount('a glass');
    }

    public function testZeroAmount(): void
    {
        $this->expectExceptionMessage('amount must be positive');
        SipLog::parseAmount('0');
    }

    public function testTotalsUndoProgress(): void
    {
        $log = new SipLog(2000);
        $log->add(250, '2026-10-19');
        $log->add('500ml', '2026-10-19');
        $log->add('33cl', '2026-10-19');
        $log->add(900, '2026-10-18');
        $this->assertSame(1080, $log->total('2026-10-19'));
        $this->assertSame(['date' => '2026-10-19', 'ml' => 330], $log->undo('2026-10-19'));
        $this->assertSame(['date' => '2026-10-19', 'total' => 750, 'goal' => 2000, 'percent' => 38, 'remaining' => 1250, 'reached' => false], $log->progress('2026-10-19'));
        $this->assertNull($log->undo('2026-10-01'));
        $this->assertSame(1, $log->clearDay('2026-10-18'));
    }

    public function testWeekAverageStreak(): void
    {
        $log = new SipLog(2000);
        foreach (['2026-10-16', '2026-10-17', '2026-10-18'] as $d) {
            $log->add(2100, $d);
        }
        $log->add(500, '2026-10-19');
        $w = $log->week('2026-10-19');
        $this->assertCount(7, $w);
        $this->assertSame(['date' => '2026-10-19', 'total' => 500], $w[0]);
        $this->assertSame(['date' => '2026-10-16', 'total' => 2100], $w[3]);
        $this->assertSame(3, $log->streak('2026-10-19'));
        $log->add(1500, '2026-10-19');
        $this->assertSame(4, $log->streak('2026-10-19'));
        $this->assertSame(1186, $log->average('2026-10-19'));
    }

    public function testJsonRoundTrip(): void
    {
        $log = new SipLog(2500);
        $log->add(400, '2026-10-19');
        $back = SipLog::fromJson($log->toJson());
        $this->assertSame(2500, $back->goal);
        $this->assertSame(400, $back->total('2026-10-19'));
        // reads the file written by the other ports
        $other = SipLog::fromJson('{"goal":2000,"entries":[{"date":"2026-10-19","ml":500},{"date":"2026-10-19","ml":250}]}');
        $this->assertSame(750, $other->total('2026-10-19'));
    }
}
