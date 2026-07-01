<?php

namespace App\Support;

use Carbon\Carbon;

class BusinessDayCalculator
{
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

    public function isBusinessDay(Carbon $date): bool
    {
        $calendar = \App\Models\BusinessCalendar::default();

        if ($calendar?->exclude_weekends && $date->isWeekend()) {
            return false;
        }

        $holidays = $calendar
            ? $calendar->holidays()->pluck('date')->map(fn ($d) => $d->toDateString())->all()
            : config('credentialing.business_days.holidays', []);

        return ! in_array($date->toDateString(), $holidays, true);
    }
}
