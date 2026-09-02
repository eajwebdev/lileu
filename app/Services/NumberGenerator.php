<?php

namespace App\Services;

use App\Models\NumberSequence;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Draws gap-free, collision-free document numbers.
 *
 * Receipt numbers reset daily (LE-YYYYMMDD-NNNNNN) and order numbers yearly
 * (RE-YYYY-NNNNNN); the year is always read from the clock, never hard-coded.
 */
class NumberGenerator
{
    public function resellerOrderNumber(?Carbon $at = null): string
    {
        $at ??= now();
        $prefix = Settings::get('order_prefix', config('lileu.receipt.order_prefix'));
        $period = $at->format('Y');

        return sprintf('%s-%s-%s', $prefix, $period, $this->pad($this->next('reseller_order', $period)));
    }

    public function receiptNumber(?Carbon $at = null): string
    {
        $at ??= now();
        $prefix = Settings::get('receipt_prefix', config('lileu.receipt.prefix'));
        $period = $at->format('Ymd');

        return sprintf('%s-%s-%s', $prefix, $period, $this->pad($this->next('receipt', $period)));
    }

    public function consignmentNumber(?Carbon $at = null): string
    {
        $at ??= now();
        $prefix = Settings::get('consignment_prefix', config('lileu.receipt.consignment_prefix'));
        $period = $at->format('Y');

        return sprintf('%s-%s-%s', $prefix, $period, $this->pad($this->next('consignment', $period)));
    }

    public function posSaleNumber(?Carbon $at = null): string
    {
        $at ??= now();
        $prefix = config('lileu.receipt.pos_prefix');
        $period = $at->format('Ymd');

        return sprintf('%s-%s-%s', $prefix, $period, $this->pad($this->next('pos_sale', $period)));
    }

    public function resellerCode(?Carbon $at = null): string
    {
        $at ??= now();
        $period = $at->format('Y');

        return sprintf('RS-%s-%s', $period, str_pad((string) $this->next('reseller', $period), 4, '0', STR_PAD_LEFT));
    }

    /** Locks the counter row so concurrent checkouts can never share a number. */
    private function next(string $series, string $period): int
    {
        return DB::transaction(function () use ($series, $period) {
            $row = NumberSequence::query()
                ->where('series', $series)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                $row = NumberSequence::create([
                    'series' => $series,
                    'period' => $period,
                    'next_value' => 1,
                ]);
            }

            $value = (int) $row->next_value;
            $row->update(['next_value' => $value + 1]);

            return $value;
        });
    }

    private function pad(int $value): string
    {
        return str_pad((string) $value, (int) config('lileu.receipt.pad', 6), '0', STR_PAD_LEFT);
    }
}
