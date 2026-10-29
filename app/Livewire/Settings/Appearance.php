<?php

namespace App\Livewire\Settings;

use App\Enums\FontSize;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Appearance settings')]
class Appearance extends Component
{
    public string $fontSize = 'default';

    public function mount(): void
    {
        $this->fontSize = Auth::user()->font_size->value;
    }

    /**
     * Saved as soon as a size is picked, and applied to the open page right away.
     */
    public function updatedFontSize(string $value): void
    {
        $size = FontSize::tryFrom($value) ?? FontSize::Default;

        $this->fontSize = $size->value;
        Auth::user()->update(['font_size' => $size]);

        $this->js("document.documentElement.style.fontSize = '{$size->scale()}'");
    }
}
