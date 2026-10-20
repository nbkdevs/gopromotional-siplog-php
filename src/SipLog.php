<?php

declare(strict_types=1);

namespace GoPromotional\SipLog;

use InvalidArgumentException;

/**
 * Hydration helpers: a daily water goal estimate (a general guide, not medical
 * advice), amount parsing ("500ml", "0.5l", "33cl", "16oz") and a day-by-day
 * water log with progress, 7-day history and streaks. The JSON format matches
 * the npm, PyPI, crates.io and RubyGems versions of gopromotional-siplog.
 */
final class SipLog
{
    public const ML_PER_US_FL_OZ = 29.5735;
    public const ML_PER_ACTIVE_MINUTE = 12;
    public const HOT_WEATHER_ML = 500;

    /** @var array<int, array{date:string, ml:int}> */
    public array $entries = [];

    public function __construct(public int $goal = 2000, array $entries = [])
    {
        foreach ($entries as $e) {
            $this->entries[] = ['date' => self::normDate((string) $e['date']), 'ml' => self::parseAmount($e['ml'])];
        }
    }

    public static function toDay(string $date): int
    {
        $date = trim($date);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
            throw new InvalidArgumentException("Invalid date \"$date\" (expected YYYY-MM-DD)");
        }
        if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new InvalidArgumentException("Invalid calendar date \"$date\"");
        }
        return intdiv(gmmktime(0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]), 86400);
    }

    public static function fromDay(int $day): string
    {
        return gmdate('Y-m-d', $day * 86400);
    }

    private static function normDate(?string $date): string
    {
        return self::fromDay(self::toDay($date ?? date('Y-m-d')));
    }

    /** Rough daily goal in ml: weight x mlPerKg + 12 ml per active minute (+500 ml when hot), to the nearest 50 ml. */
    public static function dailyGoal(float $weightKg, float $activityMinutes = 0, bool $hot = false, float $mlPerKg = 33): int
    {
        if (!($weightKg > 0) || $weightKg > 400) {
            throw new InvalidArgumentException('weightKg must be between 0 and 400');
        }
        if ($activityMinutes < 0) {
            throw new InvalidArgumentException('activityMinutes must be >= 0');
        }
        $raw = $weightKg * $mlPerKg + $activityMinutes * self::ML_PER_ACTIVE_MINUTE + ($hot ? self::HOT_WEATHER_ML : 0);
        return (int) floor($raw / 50 + 0.5) * 50;
    }

    /** Parse 500, "500ml", "0.5l", "1.5 L", "33cl" or "16oz" into whole millilitres. */
    public static function parseAmount(int|float|string $input): int
    {
        if (is_int($input) || is_float($input)) {
            if (!($input > 0)) {
                throw new InvalidArgumentException('amount must be positive');
            }
            return (int) floor($input + 0.5);
        }
        if (!preg_match('/^\s*(\d+(?:\.\d+)?)\s*(ml|cl|l|oz)?\s*$/i', $input, $m)) {
            throw new InvalidArgumentException("Cannot read amount \"$input\" (try 250, 500ml, 0.5l, 33cl or 16oz)");
        }
        $factor = ['ml' => 1, 'cl' => 10, 'l' => 1000, 'oz' => self::ML_PER_US_FL_OZ][strtolower($m[2] ?? '') ?: 'ml'];
        $ml = (int) floor((float) $m[1] * $factor + 0.5);
        if ($ml <= 0) {
            throw new InvalidArgumentException('amount must be positive');
        }
        return $ml;
    }

    public static function fromJson(string $json): self
    {
        $d = json_decode($json, true, 512, JSON_THROW_ON_ERROR) ?: [];
        return new self((int) ($d['goal'] ?? 2000), $d['entries'] ?? []);
    }

    public function toJson(): string
    {
        return json_encode(['goal' => $this->goal, 'entries' => $this->entries], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function add(int|float|string $amount, ?string $date = null): array
    {
        $entry = ['date' => self::normDate($date), 'ml' => self::parseAmount($amount)];
        $this->entries[] = $entry;
        return $entry;
    }

    /** Remove the most recent entry for a day; returns it, or null. */
    public function undo(?string $date = null): ?array
    {
        $d = self::normDate($date);
        for ($i = count($this->entries) - 1; $i >= 0; $i--) {
            if ($this->entries[$i]['date'] === $d) {
                return array_splice($this->entries, $i, 1)[0];
            }
        }
        return null;
    }

    public function clearDay(?string $date = null): int
    {
        $d = self::normDate($date);
        $before = count($this->entries);
        $this->entries = array_values(array_filter($this->entries, fn ($e) => $e['date'] !== $d));
        return $before - count($this->entries);
    }

    public function total(?string $date = null): int
    {
        $d = self::normDate($date);
        return array_sum(array_map(fn ($e) => $e['date'] === $d ? $e['ml'] : 0, $this->entries));
    }

    public function progress(?string $date = null, ?int $goal = null): array
    {
        $goal ??= $this->goal;
        $total = $this->total($date);
        return [
            'date' => self::normDate($date), 'total' => $total, 'goal' => $goal,
            'percent' => $goal > 0 ? (int) floor($total * 100 / $goal + 0.5) : 0,
            'remaining' => max(0, $goal - $total), 'reached' => $total >= $goal,
        ];
    }

    /** Totals for the 7 days ending on $endDate, newest first. */
    public function week(?string $endDate = null): array
    {
        $end = self::toDay(self::normDate($endDate));
        $out = [];
        for ($i = 0; $i < 7; $i++) {
            $d = self::fromDay($end - $i);
            $out[] = ['date' => $d, 'total' => $this->total($d)];
        }
        return $out;
    }

    /** Consecutive days meeting the goal, ending today (or yesterday if today is not done yet). */
    public function streak(?string $endDate = null, ?int $goal = null): int
    {
        $goal ??= $this->goal;
        $day = self::toDay(self::normDate($endDate));
        if ($this->total(self::fromDay($day)) < $goal) {
            $day--;
        }
        $n = 0;
        while ($n < 3660 && $this->total(self::fromDay($day)) >= $goal) {
            $n++;
            $day--;
        }
        return $n;
    }

    public function average(?string $endDate = null, int $days = 7): int
    {
        $end = self::toDay(self::normDate($endDate));
        $sum = 0;
        for ($i = 0; $i < $days; $i++) {
            $sum += $this->total(self::fromDay($end - $i));
        }
        return (int) floor($sum / $days + 0.5);
    }
}
