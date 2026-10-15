<?php

namespace App\Livewire\Admin;

use App\Enums\TicketPriority;
use App\Models\BusinessHour;
use App\Models\Holiday;
use App\Models\SlaPolicy;
use App\Services\TicketManager;
use App\Support\BusinessCalendar;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Business Hours')]
class BusinessHours extends Component
{
    /**
     * The office week, keyed by ISO weekday (1 = Monday).
     *
     * @var array<int, array{open: bool, opens_at: string, closes_at: string}>
     */
    public array $days = [];

    public string $holidayDate = '';

    public string $holidayName = '';

    public function mount(): void
    {
        $hours = BusinessHour::query()->get()->keyBy('weekday');

        foreach (range(1, 7) as $weekday) {
            $hour = $hours->get($weekday);

            $this->days[$weekday] = [
                'open' => $hour !== null,
                'opens_at' => $hour ? substr($hour->opens_at, 0, 5) : '09:00',
                'closes_at' => $hour ? substr($hour->closes_at, 0, 5) : '18:00',
            ];
        }
    }

    public function saveHours(TicketManager $manager): void
    {
        $rules = [];

        foreach (range(1, 7) as $weekday) {
            if ($this->days[$weekday]['open'] ?? false) {
                $rules["days.{$weekday}.opens_at"] = ['required', 'date_format:H:i'];
                $rules["days.{$weekday}.closes_at"] = ['required', 'date_format:H:i', "after:days.{$weekday}.opens_at"];
            }
        }

        $this->validate($rules, [
            'days.*.closes_at.after' => __('Closing time must be after opening time.'),
        ], ['days.*.opens_at' => __('opening time'), 'days.*.closes_at' => __('closing time')]);

        DB::transaction(function () {
            BusinessHour::query()->delete();

            foreach ($this->days as $weekday => $day) {
                if ($day['open']) {
                    BusinessHour::create(['weekday' => $weekday, 'opens_at' => $day['opens_at'], 'closes_at' => $day['closes_at']]);
                }
            }
        });

        // A week with no open days never fires a model event, so flush explicitly.
        BusinessCalendar::flush();

        $this->recalculated($manager, __('Business hours saved.'));
    }

    public function addHoliday(TicketManager $manager): void
    {
        $this->validate([
            'holidayDate' => [
                'required',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (Holiday::query()->whereDate('date', $value)->exists()) {
                        $fail(__('That date is already a holiday.'));
                    }
                },
            ],
            'holidayName' => ['required', 'string', 'max:100'],
        ], attributes: ['holidayDate' => __('date'), 'holidayName' => __('name')]);

        Holiday::create(['date' => $this->holidayDate, 'name' => $this->holidayName]);

        $this->reset('holidayDate', 'holidayName');
        unset($this->holidays);

        $this->recalculated($manager, __('Holiday added.'));
    }

    public function deleteHoliday(Holiday $holiday, TicketManager $manager): void
    {
        $holiday->delete();
        unset($this->holidays);

        $this->recalculated($manager, __('Holiday removed.'));
    }

    /**
     * Holidays from the start of last year onwards, so the list stays short.
     *
     * @return Collection<int, Holiday>
     */
    #[Computed]
    public function holidays(): Collection
    {
        return Holiday::query()
            ->where('date', '>=', now()->subYear()->startOfYear())
            ->orderBy('date')
            ->get();
    }

    /**
     * Priorities set to run on business hours, for the summary at the top.
     *
     * @return list<TicketPriority>
     */
    #[Computed]
    public function businessPriorities(): array
    {
        return array_values(array_filter(TicketPriority::cases(), fn (TicketPriority $priority) => SlaPolicy::usesBusinessHours($priority)));
    }

    #[Computed]
    public function calendar(): BusinessCalendar
    {
        return app(BusinessCalendar::class);
    }

    /**
     * The current moment in the office's own timezone.
     */
    #[Computed]
    public function localNow(): CarbonImmutable
    {
        return now()->setTimezone($this->calendar->timezone());
    }

    /**
     * Re-time open tickets against the new calendar and report back.
     */
    protected function recalculated(TicketManager $manager, string $message): void
    {
        unset($this->calendar, $this->businessPriorities);

        $moved = $manager->recalculateOpenDueDates();

        Flux::toast(variant: 'success', text: $moved > 0
            ? $message.' '.trans_choice('Deadlines updated on :count open ticket.|Deadlines updated on :count open tickets.', $moved)
            : $message);
    }
}
