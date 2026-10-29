<?php

namespace App\Enums;

/**
 * An enum with a translated label to show people.
 */
interface HasLabel
{
    public function label(): string;
}
