<?php

namespace App\Models\Concerns;

/**
 * A sold line names the product and, when there was one, the flavour.
 *
 * Both are snapshots taken at the time of sale, so a receipt still reads
 * correctly after the flavour has been renamed or removed from the menu.
 */
trait NamesASoldItem
{
    public function getDisplayNameAttribute(): string
    {
        return $this->variant_name
            ? $this->product_name.' — '.$this->variant_name
            : (string) $this->product_name;
    }
}
