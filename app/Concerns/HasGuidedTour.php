<?php

namespace App\Concerns;

use App\Enums\Tour;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;

/**
 * A page with its own guided tour (<x-tour>): shown to a client the first time
 * they open the page, or again when they ask for it from the User Manual (?tour=1).
 */
trait HasGuidedTour
{
    #[Locked]
    public bool $showTour = false;

    abstract protected function guidedTour(): Tour;

    public function mountHasGuidedTour(): void
    {
        $this->showTour = Auth::user()->needsTour($this->guidedTour(), request()->boolean('tour'));

        if ($this->showTour) {
            $this->tourStarted();
        }
    }

    /**
     * Called by the tour when the user finishes or skips it, so it does not come back.
     */
    public function finishTour(): void
    {
        Auth::user()->completeTour($this->guidedTour());

        $this->showTour = false;
        $this->tourEnded();
    }

    /**
     * For a page that fills itself with example content for the tour...
     */
    protected function tourStarted(): void {}

    /**
     * ...and puts its real content back once the tour is over.
     */
    protected function tourEnded(): void {}
}
