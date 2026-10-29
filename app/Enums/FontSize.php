<?php

namespace App\Enums;

/**
 * Text size chosen under Settings → Appearance. It scales the root font size,
 * so spacing sized in rem grows with the text.
 */
enum FontSize: string
{
    case Small = 'small';
    case Default = 'default';
    case Large = 'large';
    case ExtraLarge = 'extra-large';

    public function label(): string
    {
        return match ($this) {
            self::Small => __('Small'),
            self::Default => __('Default'),
            self::Large => __('Large'),
            self::ExtraLarge => __('Extra large'),
        };
    }

    /**
     * The root font size, relative to the browser's own (usually 16px).
     */
    public function scale(): string
    {
        return match ($this) {
            self::Small => '87.5%',
            self::Default => '100%',
            self::Large => '112.5%',
            self::ExtraLarge => '125%',
        };
    }
}
