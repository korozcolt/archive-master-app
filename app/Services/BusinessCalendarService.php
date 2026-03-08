<?php

namespace App\Services;

use App\Models\BusinessCalendar;
use App\Models\Company;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class BusinessCalendarService
{
    /**
     * @return array<int, int>
     */
    public function weekendDays(?BusinessCalendar $calendar = null): array
    {
        $days = $calendar?->weekend_days;

        if (is_array($days) && $days !== []) {
            return array_map(static fn (mixed $day): int => (int) $day, $days);
        }

        return [0, 6];
    }

    public function defaultCalendarForCompany(Company $company): ?BusinessCalendar
    {
        return $company->businessCalendars()->where('is_default', true)->first();
    }

    public function isBusinessDay(CarbonInterface $date, Company $company, ?BusinessCalendar $calendar = null): bool
    {
        $calendar ??= $this->defaultCalendarForCompany($company);
        $date = CarbonImmutable::instance($date);

        $override = $calendar?->days()->whereDate('date', $date->toDateString())->first();
        if ($override !== null) {
            return (bool) $override->is_business_day;
        }

        return ! in_array($date->dayOfWeek, $this->weekendDays($calendar), true);
    }

    public function addBusinessDays(CarbonInterface $startDate, int $days, Company $company, ?BusinessCalendar $calendar = null): CarbonImmutable
    {
        $calendar ??= $this->defaultCalendarForCompany($company);
        $cursor = CarbonImmutable::instance($startDate);
        $remaining = max($days, 0);

        while ($remaining > 0) {
            $cursor = $cursor->addDay();

            if ($this->isBusinessDay($cursor, $company, $calendar)) {
                $remaining--;
            }
        }

        while (! $this->isBusinessDay($cursor, $company, $calendar)) {
            $cursor = $cursor->addDay();
        }

        return $cursor;
    }

    public function subtractBusinessDays(CarbonInterface $startDate, int $days, Company $company, ?BusinessCalendar $calendar = null): CarbonImmutable
    {
        $calendar ??= $this->defaultCalendarForCompany($company);
        $cursor = CarbonImmutable::instance($startDate);
        $remaining = max($days, 0);

        while ($remaining > 0) {
            $cursor = $cursor->subDay();

            if ($this->isBusinessDay($cursor, $company, $calendar)) {
                $remaining--;
            }
        }

        while (! $this->isBusinessDay($cursor, $company, $calendar)) {
            $cursor = $cursor->subDay();
        }

        return $cursor;
    }

    public function businessDaysDifference(CarbonInterface $from, CarbonInterface $to, Company $company, ?BusinessCalendar $calendar = null): int
    {
        $calendar ??= $this->defaultCalendarForCompany($company);
        $fromDate = CarbonImmutable::instance($from)->startOfDay();
        $toDate = CarbonImmutable::instance($to)->startOfDay();

        if ($fromDate->equalTo($toDate)) {
            return 0;
        }

        $direction = $fromDate->lessThan($toDate) ? 1 : -1;
        $cursor = $fromDate;
        $days = 0;

        while (! $cursor->equalTo($toDate)) {
            $cursor = $direction === 1 ? $cursor->addDay() : $cursor->subDay();

            if ($this->isBusinessDay($cursor, $company, $calendar)) {
                $days += $direction;
            }
        }

        return $days;
    }

    public function businessDaysUntil(CarbonInterface $from, CarbonInterface $to, Company $company, ?BusinessCalendar $calendar = null): int
    {
        return max(0, $this->businessDaysDifference($from, $to, $company, $calendar));
    }
}
