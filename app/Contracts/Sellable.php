<?php

namespace App\Contracts;

/**
 * Something that can be put on a receipt.
 *
 * A product without variants is sellable on its own; a product with variants
 * is not — each of its flavours is. Selling code asks for a Sellable and stops
 * caring which of the two it got.
 */
interface Sellable
{
    public function sellableProductId(): int;

    public function sellableVariantId(): ?int;

    /** The product name, without the flavour. */
    public function sellableProductName(): string;

    /** The flavour, when there is one. */
    public function sellableVariantName(): ?string;

    /** Product and flavour together, for anything that shows one line of text. */
    public function sellableLabel(): string;

    public function sellableSku(): ?string;

    public function retailPrice(): float;

    public function resellerPrice(): float;

    public function costPrice(): float;

    public function availableStock(): int;

    public function tracksStock(): bool;

    public function isManuallyAvailable(): bool;

    public function decrementStock(int $quantity): void;

    public function incrementStock(int $quantity): void;
}
