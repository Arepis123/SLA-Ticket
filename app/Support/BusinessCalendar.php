<?php

namespace App\Support;

use App\Models\BusinessHour;
use App\Models\Holiday;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Office hours and public holidays, and the time arithmetic that skips everything
 * outside them. Times are interpreted in the SLA timezone and returned in the app's.
 *
 * With no opening hours configured at all the calendar falls back to 24/7, so a
 * misconfigured week can never stall the clock forever.
 */
class BusinessCalendar
{
    /**
     * How far ahead (or across) we are willing to walk, in days, before giving up.
     */
    protected const MAX_DAYS = 3660;

    /**
     * Opening window per ISO weekday, as "H:i:s" strings.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    protected array $hours;

    /**
     * Closed dates, keyed by "Y-m-d".
     *
     * @var array<string, true>
     */
    protected array $holidays;

    public function __construct()
    {
        ['hours' => $this->hours, 'holidays' => $this->holidays] = Cache::memo()->rememberForever('business-calendar', fn () => [
            'hours' => BusinessHour::query()
                ->get()
                ->mapWithKeys(fn (BusinessHour $hour) => [$hour->weekday => [
                    self::normalizeTime($hour->opens_at),
                    self::normalizeTime($hour->closes_at),
                ]])
                ->all(),
            'holidays' => Holiday::query()
                ->get()
                ->mapWithKeys(fn (Holiday $holiday) => [$holiday->date->format('Y-m-d') => true])
                ->all(),
        ]);
    }

    /**
     * Forget the cached schedule after the hours or holidays change.
     */
    public static function flush(): void
    {
        Cache::memo()->forget('business-calendar');
    }

    public function timezone(): string
    {
        return config('sla.timezone') ?: config('app.timezone');
    }

    /**
     * Whether any opening hours are configured. Without them the calendar is 24/7.
     */
    public function hasHours(): bool
    {
        return $this->hours !== [];
    }

    public function isHoliday(CarbonInterface $moment): bool
    {
        return isset($this->holidays[$this->local($moment)->format('Y-m-d')]);
    }

    /**
     * Whether the office is open at the given moment.
     */
    public function isOpenAt(CarbonInterface $moment): bool
    {
        if (! $this->hasHours()) {
            return true;
        }

        $moment = $this->local($moment);
        $window = $this->window($moment->startOfDay());

        return $window !== null && $moment->greaterThanOrEqualTo($window[0]) && $moment->lessThan($window[1]);
    }

    /**
     * The moment the given number of business minutes after the start has elapsed.
     */
    public function addMinutes(CarbonInterface $start, int $minutes): CarbonImmutable
    {
        $start = CarbonImmutable::instance($start);

        if ($minutes <= 0 || ! $this->hasHours()) {
            return $start->addMinutes(max(0, $minutes));
        }

        $remaining = $minutes * 60;
        $cursor = $this->local($start);

        for ($i = 0; $i < self::MAX_DAYS; $i++) {
            if ($window = $this->window($cursor->startOfDay())) {
                [$opens, $closes] = $window;
                $from = $cursor->max($opens);

                if ($from->lessThan($closes)) {
                    $available = (int) $from->diffInSeconds($closes);

                    if ($remaining <= $available) {
                        return $from->addSeconds($remaining)->setTimezone(config('app.timezone'));
                    }

                    $remaining -= $available;
                }
            }

            $cursor = $cursor->startOfDay()->addDay();
        }

        return $cursor->addSeconds($remaining)->setTimezone(config('app.timezone'));
    }

    /**
     * Business seconds between two moments; zero when the end is not after the start.
     */
    public function secondsBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        if (! $this->hasHours()) {
            return max(0, (int) $from->diffInSeconds($to, false));
        }

        $from = $this->local($from);
        $to = $this->local($to);

        if (! $to->greaterThan($from)) {
            return 0;
        }

        $total = 0;
        $day = $from->startOfDay();

        for ($i = 0; $i < self::MAX_DAYS && $day->lessThan($to); $i++, $day = $day->addDay()) {
            if ($window = $this->window($day)) {
                $start = $from->max($window[0]);
                $end = $to->min($window[1]);

                if ($start->lessThan($end)) {
                    $total += (int) $start->diffInSeconds($end);
                }
            }
        }

        return $total;
    }

    public function minutesBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        return intdiv($this->secondsBetween($from, $to), 60);
    }

    /**
     * Opening hours for display, keyed by ISO weekday, as "H:i" pairs. Closed days are absent.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public function schedule(): array
    {
        return array_map(fn (array $window) => [substr($window[0], 0, 5), substr($window[1], 0, 5)], $this->hours);
    }

    /**
     * The week in words, grouping runs of days with the same hours, e.g.
     * "Mon–Fri 09:00–18:00, Sat 09:00–13:00".
     */
    public function describe(): string
    {
        $groups = [];
        $schedule = $this->schedule();
        ksort($schedule);

        foreach ($schedule as $weekday => [$opens, $closes]) {
            $last = array_key_last($groups);

            if ($last !== null && $groups[$last]['to'] === $weekday - 1 && $groups[$last]['hours'] === "{$opens}–{$closes}") {
                $groups[$last]['to'] = $weekday;
            } else {
                $groups[] = ['from' => $weekday, 'to' => $weekday, 'hours' => "{$opens}–{$closes}"];
            }
        }

        $day = fn (int $weekday) => CarbonImmutable::create(2024, 1, $weekday)->translatedFormat('D'); // 1 Jan 2024 was a Monday

        return implode(', ', array_map(fn (array $group) => ($group['from'] === $group['to']
            ? $day($group['from'])
            : $day($group['from']).'–'.$day($group['to'])).' '.$group['hours'], $groups));
    }

    /**
     * The opening and closing moment on the given local day, or null when closed.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    protected function window(CarbonImmutable $day): ?array
    {
        if (isset($this->holidays[$day->format('Y-m-d')]) || ! isset($this->hours[$day->isoWeekday()])) {
            return null;
        }

        [$opens, $closes] = $this->hours[$day->isoWeekday()];

        return [$day->setTimeFromTimeString($opens), $day->setTimeFromTimeString($closes)];
    }

    protected function local(CarbonInterface $moment): CarbonImmutable
    {
        return CarbonImmutable::instance($moment)->setTimezone($this->timezone());
    }

    /**
     * Databases hand times back as "09:00" or "09:00:00"; settle on the latter.
     */
    protected static function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? "{$time}:00" : $time;
    }
}
