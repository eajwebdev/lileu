@php
    /** 80mm thermal slip for the counter. Deliberately narrow and ink-light. */
    $clean = fn (?string $text) => (string) $text;
    $peso = fn ($value) => '₱' . number_format((float) $value, 2);

    $logoData = ($options['show_logo'] ?? false) ? \App\Support\Settings::logoDataUri() : null;

    $methodLabels = [
        'cash' => 'Cash',
        'gcash' => 'GCash',
        'qrph' => 'QR Ph',
        'card' => 'Card',
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $sale->sale_number }} · {{ $brand['name'] }}</title>
    <style>
        @page { size: 80mm auto; margin: 3mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 10px;
            font-family: 'Plus Jakarta Sans', 'Segoe UI', system-ui, sans-serif;
            font-size: 11px; line-height: 1.45; color: #3B2A22; background: #F5E8D9;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
        .slip { width: 100%; max-width: 74mm; margin: 0 auto; background: #FFFDFC; border: 1px solid #EBD8C3; }
        .head { background: #3B2A22; color: #FBF5EC; padding: 12px 10px; text-align: center; }
        .head .logo { width: 42px; height: 42px; border-radius: 50%; background: #FBF5EC; margin-bottom: 6px; }
        .head .name { font-size: 15px; font-weight: 700; }
        .head .tag { font-size: 8px; letter-spacing: 0.18em; text-transform: uppercase; color: #EFAFB8; margin-top: 2px; }
        .head .addr { font-size: 8.5px; color: rgba(251,245,236,0.7); margin-top: 6px; }
        .pad { padding: 10px; }
        .meta { width: 100%; border-collapse: collapse; font-size: 9.5px; color: #6B5044; }
        .meta td { padding: 1px 0; }
        .meta td:last-child { text-align: right; color: #3B2A22; font-weight: 600; }
        .mono { font-family: 'DejaVu Sans Mono', 'Courier New', monospace; }
        .rule { border-top: 1px dashed rgba(59,42,34,0.3); margin: 9px 0; }
        .items { width: 100%; border-collapse: collapse; font-size: 10.5px; }
        .items td { padding: 3px 0; vertical-align: top; }
        .items .qty { width: 26px; color: #8A6B5A; }
        .items .amt { text-align: right; font-weight: 600; white-space: nowrap; }
        .items .unit { font-size: 9px; color: #8A6B5A; }
        .sums { width: 100%; border-collapse: collapse; font-size: 10.5px; }
        .sums td { padding: 2px 0; color: #6B5044; }
        .sums td:last-child { text-align: right; }
        .total { background: #2B1B16; color: #FBF5EC; padding: 9px 10px; }
        .total table { width: 100%; border-collapse: collapse; }
        .total .label { font-size: 9px; letter-spacing: 0.18em; text-transform: uppercase; color: #EFAFB8; font-weight: 700; }
        .total .amount { text-align: right; font-size: 17px; font-weight: 700; }
        .stamp { display: inline-block; border: 2px solid #2F7D4A; color: #2F7D4A; padding: 4px 12px; border-radius: 8px; font-size: 11px; font-weight: 700; letter-spacing: 0.08em; }
        .foot { text-align: center; padding: 10px; border-top: 1px dashed rgba(59,42,34,0.3); }
        .foot .msg { font-size: 11px; font-weight: 700; }
        .foot .meta2 { font-size: 8.5px; color: #8A6B5A; margin-top: 3px; }
        .no-print { text-align: center; padding: 12px; }
        .no-print button { font: inherit; font-weight: 600; cursor: pointer; border: 0; border-radius: 10px; background: #3B2A22; color: #FBF5EC; padding: 9px 18px; }
        @media print { body { background: #fff; padding: 0; } .no-print { display: none !important; } .slip { border: 0; } }
    </style>
</head>
<body>
<div class="no-print">
    <button type="button" onclick="window.print()">Print slip</button>
</div>

<div class="slip">
    <div class="head">
        @if ($logoData)
            <img class="logo" src="{{ $logoData }}" alt="">
        @endif
        <div class="name">{{ $brand['name'] }}</div>
        <div class="tag">{{ $brand['tagline'] }}</div>
        <div class="addr">{{ $brand['address'] }}<br>{{ $brand['phone'] }}</div>
    </div>

    <div class="pad">
        <table class="meta">
            <tr>
                <td>Sale No.</td>
                <td class="mono">{{ $sale->sale_number }}</td>
            </tr>
            <tr>
                <td>Date</td>
                <td>{{ $sale->created_at->format('M j, Y g:i A') }}</td>
            </tr>
            <tr>
                <td>Cashier</td>
                <td>{{ $sale->cashier?->name ?? '—' }}</td>
            </tr>
            @if ($sale->customer_name)
                <tr>
                    <td>Customer</td>
                    <td>{{ $sale->customer_name }}</td>
                </tr>
            @endif
            <tr>
                <td>Payment</td>
                <td>{{ $methodLabels[$sale->method] ?? ucfirst($sale->method) }}</td>
            </tr>
        </table>

        <div class="rule"></div>

        <table class="items">
            @foreach ($sale->items as $item)
                <tr>
                    <td class="qty">{{ $item->quantity }}×</td>
                    <td>
                        {{ $item->display_name }}
                        <div class="unit">@ {{ $peso($item->unit_price) }}</div>
                    </td>
                    <td class="amt">{{ $peso($item->line_total) }}</td>
                </tr>
            @endforeach
        </table>

        <div class="rule"></div>

        <table class="sums">
            <tr>
                <td>Subtotal</td>
                <td>{{ $peso($sale->subtotal) }}</td>
            </tr>
            @if ($sale->discount > 0)
                <tr>
                    <td>Discount</td>
                    <td>−{{ $peso($sale->discount) }}</td>
                </tr>
            @endif
        </table>
    </div>

    <div class="total">
        <table>
            <tr>
                <td class="label">Total</td>
                <td class="amount">{{ $peso($sale->total) }}</td>
            </tr>
        </table>
    </div>

    <div class="pad">
        <table class="sums">
            <tr>
                <td>Amount tendered</td>
                <td>{{ $peso($sale->amount_tendered) }}</td>
            </tr>
            <tr>
                <td>Change</td>
                <td>{{ $peso($sale->change_due) }}</td>
            </tr>
        </table>

        <div style="text-align: center; margin-top: 12px;">
            <span class="stamp">{{ $sale->status === 'void' ? 'VOID' : 'PAID' }}</span>
        </div>
    </div>

    <div class="foot">
        <div class="msg">Thank you for ordering at {{ $brand['name'] }}.</div>
        <div class="meta2">{{ collect([$brand['facebook'], $brand['website']])->filter()->implode(' · ') }}</div>
    </div>
</div>

@if ($autoPrint ?? false)
    <script>
        window.addEventListener('load', () => setTimeout(() => window.print(), 300));
    </script>
@endif
</body>
</html>
