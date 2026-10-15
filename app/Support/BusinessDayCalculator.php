<?php

namespace App\Support;

use App\Models\BusinessCalendar;
use App\Models\Holiday;
use Carbon\Carbon;

class BusinessDayCalculator
{
    protected static bool $defaultCalendarResolved = false;

    protected static ?BusinessCalendar $defaultCalendar = null;

    /** @var array<string, array<string, bool>> */
    protected static array $holidayLookupCache = [];

    protected static bool $listenersRegistered = false;

    public static function clearCache(): void
    {
        self::$defaultCalendarResolved = false;
        self::$defaultCalendar = null;
        self::$holidayLookupCache = [];
    }

    protected static function ensureModelListeners(): void
    {
        if (self::$listenersRegistered) {
            return;
        }

        self::$listenersRegistered = true;

        BusinessCalendar::saved(fn () => self::clearCache());
        BusinessCalendar::deleted(fn () => self::clearCache());
        Holiday::saved(fn () => self::clearCache());
        Holiday::deleted(fn () => self::clearCache());
    }

    public function addDays(Carbon $start, int $days, bool $businessDaysOnly = true): Carbon
    {
        $date = $start->copy();

        if (! $businessDaysOnly) {
            return $date->addDays($days);
        }

        $added = 0;
        while ($added < $days) {
            $date->addDay();
            if ($this->isBusinessDay($date)) {
                $added++;
            }
        }

        return $date;
    }

    public function addBusinessDays(Carbon $start, int $days, ?BusinessCalendar $calendar = null): Carbon
    {
        $date = $start->copy();

        $added = 0;
        while ($added < $days) {
            $date->addDay();
            if ($this->isBusinessDay($date, $calendar)) {
                $added++;
            }
        }

        return $date;
    }

    public function businessDaysBetween(Carbon $start, Carbon $end, ?BusinessCalendar $calendar = null): int
    {
        $from = $start->copy()->startOfDay();
        $to = $end->copy()->startOfDay();

        if ($from->greaterThanOrEqualTo($to)) {
            return 0;
        }

        $count = 0;
        $cursor = $from->copy();
        while ($cursor->lessThan($to)) {
            $cursor->addDay();
            if ($this->isBusinessDay($cursor, $calendar)) {
                $count++;
            }
        }

        return $count;
    }

    public function isBusinessDay(Carbon $date, ?BusinessCalendar $calendar = null): bool
    {
        $resolvedCalendar = $calendar ?? $this->resolveCalendar();

        $excludeWeekends = $resolvedCalendar
            ? (bool) $resolvedCalendar->exclude_weekends
            : (bool) config('credentialing.business_days.exclude_weekends', true);

        if ($excludeWeekends && $date->isWeekend()) {
            return false;
        }

        $holidayLookup = $this->resolveHolidayLookup($resolvedCalendar);

        return ! isset($holidayLookup[$date->toDateString()]);
    }

    protected function resolveCalendar(): ?BusinessCalendar
    {
        self::ensureModelListeners();

        if (! self::$defaultCalendarResolved) {
            self::$defaultCalendar = BusinessCalendar::default();
            self::$defaultCalendarResolved = true;
        }

        return self::$defaultCalendar;
    }

    /** @return array<string, bool> */
    protected function resolveHolidayLookup(?BusinessCalendar $calendar): array
    {
        self::ensureModelListeners();

        $cacheKey = $calendar ? 'calendar:'.$calendar->id : 'config';

        if (! array_key_exists($cacheKey, self::$holidayLookupCache)) {
            $dates = $calendar
                ? $calendar->holidays()->pluck('date')->map(fn ($d) => $d->toDateString())->all()
                : (array) config('credentialing.business_days.holidays', []);

            self::$holidayLookupCache[$cacheKey] = array_fill_keys($dates, true);
        }

        return self::$holidayLookupCache[$cacheKey];
    }
}

