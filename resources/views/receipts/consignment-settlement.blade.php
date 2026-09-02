@php
    /**
     * Consignment settlement receipt — one collection event.
     *
     * Draws its number from the same LE- series as reseller receipts so the
     * business keeps one continuous receipt book, and shows every unit
     * accounted for so the seller can see exactly what they were charged for.
     */
    $isPdf = $isPdf ?? false;
    $totals = $data['totals'];

    $clean = fn (?string $text) => $isPdf
        ? trim(preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]/u', '', (string) $text))
        : (string) $text;

    $peso = fn ($value) => '₱' . number_format((float) $value, 2);

    $shortfall = round($settlement['sold_value'] - $settlement['amount_collected'], 2);
    $stampLabel = $shortfall > 0.005 ? 'PARTIALLY COLLECTED' : 'COLLECTED IN FULL';
    $stampColor = $shortfall > 0.005 ? '#AC7526' : '#2F7D4A';

    $logoData = $options['show_logo'] ? \App\Support\Settings::logoDataUri() : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Settlement {{ $settlement['receipt_number'] }} · {{ $clean($brand['name']) }}</title>
    <style>
        @page { margin: 12mm; size: A4; }
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 0;
            font-family: {{ $isPdf ? "'DejaVu Sans', sans-serif" : "'Plus Jakarta Sans', 'Segoe UI', system-ui, sans-serif" }};
            font-size: 12px; color: #3B2A22; background: #F5E8D9;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
        .sheet { width: 100%; max-width: 190mm; margin: 0 auto; background: #F5E8D9; }
        .header { background: #3B2A22; color: #FBF5EC; padding: 20px 26px; }
        .header table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .brand-name { font-size: 21px; font-weight: 700; }
        .brand-tagline { font-size: 9px; letter-spacing: 0.18em; text-transform: uppercase; color: #EFAFB8; margin-top: 2px; }
        .doc-type { font-size: 9px; letter-spacing: 0.22em; text-transform: uppercase; color: rgba(251,245,236,0.7); margin-top: 8px; font-weight: 700; }
        .rcpt-label { font-size: 8px; letter-spacing: 0.16em; text-transform: uppercase; color: #EFAFB8; font-weight: 700; }
        .rcpt-number { font-family: 'DejaVu Sans Mono', 'Courier New', monospace; font-size: 15px; font-weight: 700; }
        .rcpt-order { font-size: 9px; color: rgba(251,245,236,0.6); margin-top: 6px; }
        .logo { width: 44px; height: auto; }
        .body { padding: 20px 26px; background: #F5E8D9; }
        .info { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .info td { padding: 0 10px 12px 0; vertical-align: top; width: 25%; }
        .info-label { font-size: 8px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: #8A6B5A; }
        .info-value { font-size: 12px; font-weight: 600; margin-top: 2px; }
        .mono { font-family: 'DejaVu Sans Mono', 'Courier New', monospace; }
        .items { width: 100%; border-collapse: collapse; }
        .items thead th { font-size: 8px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: #523C33; padding-bottom: 6px; border-bottom: 2px solid #3B2A22; text-align: left; }
        .items tbody td { padding: 7px 0; border-bottom: 1px solid rgba(59,42,34,0.16); }
        .num { text-align: right; }
        .ctr { text-align: center; }
        .item-name { font-weight: 600; }
        .loss { color: #9C111D; }
        .totals { width: 52%; border-collapse: collapse; margin-top: 16px; margin-left: auto; }
        .totals td { padding: 3px 0; color: #6B5044; }
        .totals td.num { text-align: right; }
        .totals tr.strong td { font-weight: 700; color: #3B2A22; }
        .total-bar { background: #2B1B16; color: #FBF5EC; padding: 14px 26px; }
        .total-bar table { width: 100%; border-collapse: collapse; }
        .total-bar .label { font-size: 9px; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; color: #EFAFB8; }
        .total-bar .amount { text-align: right; font-size: 24px; font-weight: 700; }
        .balance-row td { padding: 8px 10px; font-weight: 700; font-size: 13px; }
        .balance-due td { background: rgba(215,151,62,0.2); color: #AC7526; }
        .balance-clear td { background: #E4F1E8; color: #2F7D4A; }
        .stamp-row { width: 100%; border-collapse: collapse; margin-top: 24px; }
        .stamp-row td { vertical-align: bottom; }
        .stamp-meta { font-size: 9.5px; color: #8A6B5A; line-height: 1.6; }
        .stamp { display: inline-block; border: 3px solid {{ $stampColor }}; color: {{ $stampColor }}; padding: 9px 18px; border-radius: 10px; font-size: 14px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; white-space: nowrap; }
        .footer { border-top: 1px solid #EBD8C3; background: #FDFAF5; padding: 14px 26px; text-align: center; }
        .footer-msg { font-size: 13px; font-weight: 700; }
        .footer-meta { font-size: 9px; color: #8A6B5A; margin-top: 4px; }
        .no-print { text-align: center; padding: 14px; }
        .no-print button { font: inherit; font-weight: 600; cursor: pointer; border: 0; border-radius: 10px; background: #3B2A22; color: #FBF5EC; padding: 9px 18px; }
        @media print { body { background: #fff; } .no-print { display: none !important; } }
    </style>
</head>
<body>
@unless ($isPdf)
    <div class="no-print"><button type="button" onclick="window.print()">Print receipt</button></div>
@endunless

<div class="sheet">
    <div class="header">
        <table>
            <tr>
                <td>
                    <table>
                        <tr>
                            @if ($logoData)
                                <td style="width: 54px;"><img class="logo" src="{{ $logoData }}" alt=""></td>
                            @endif
                            <td>
                                <div class="brand-name">{{ $clean($brand['name']) }}</div>
                                <div class="brand-tagline">{{ $clean($brand['tagline']) }}</div>
                                <div class="doc-type">Consignment Settlement</div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="text-align: right;">
                    <div class="rcpt-label">Receipt No.</div>
                    <div class="rcpt-number">{{ $settlement['receipt_number'] }}</div>
                    <div class="rcpt-order">Batch {{ $data['number'] }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="body">
        <table class="info">
            <tr>
                <td>
                    <div class="info-label">Collected On</div>
                    <div class="info-value">{{ $settlement['settled_on'] }}</div>
                </td>
                <td>
                    <div class="info-label">Seller</div>
                    <div class="info-value">{{ $data['seller_name'] }}</div>
                </td>
                <td>
                    <div class="info-label">Contact</div>
                    <div class="info-value">{{ $data['seller_phone'] }}</div>
                </td>
                <td>
                    <div class="info-label">Payment</div>
                    <div class="info-value">{{ $settlement['method_label'] }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="info-label">Batch Issued</div>
                    <div class="info-value">{{ $data['issued_on'] }}</div>
                </td>
                <td>
                    <div class="info-label">Received By</div>
                    <div class="info-value">{{ $settlement['recorded_by'] ?? '—' }}</div>
                </td>
                @if ($settlement['reference'])
                    <td>
                        <div class="info-label">Reference</div>
                        <div class="info-value mono">{{ $settlement['reference'] }}</div>
                    </td>
                @endif
                @if ($settlement['notes'])
                    <td>
                        <div class="info-label">Notes</div>
                        <div class="info-value">{{ $settlement['notes'] }}</div>
                    </td>
                @endif
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="ctr">Sold</th>
                    <th class="ctr">Returned</th>
                    <th class="ctr">Expired</th>
                    <th class="ctr">Damaged</th>
                    <th class="ctr">Missing / other</th>
                    <th class="num">Unit Price</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($settlement['items'] as $item)
                    <tr>
                        <td class="item-name">{{ $item['name'] }}</td>
                        <td class="ctr">{{ $item['sold'] ?: '—' }}</td>
                        <td class="ctr">{{ $item['returned'] ?: '—' }}</td>
                        <td class="ctr loss">{{ $item['expired'] ?: '—' }}</td>
                        <td class="ctr loss">{{ $item['damaged'] ?: '—' }}</td>
                        <td class="ctr loss">{{ $item['missing'] ?: '—' }}</td>
                        <td class="num">{{ $peso($item['unit_price']) }}</td>
                        <td class="num item-name">{{ $peso($item['sold_value']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="total-bar">
        <table>
            <tr>
                <td class="label">Due For This Collection</td>
                <td class="amount">{{ $peso($settlement['sold_value']) }}</td>
            </tr>
        </table>
    </div>

    <div class="body">
        <table class="totals" style="width: 100%; margin-top: 0;">
            <tr class="strong">
                <td>Amount collected ({{ $settlement['method_label'] }})</td>
                <td class="num">{{ $peso($settlement['amount_collected']) }}</td>
            </tr>
        </table>

        @if ($shortfall > 0.005)
            <table class="totals" style="width: 100%; margin-top: 8px;">
                <tr class="balance-row balance-due">
                    <td>Short on this collection</td>
                    <td class="num">{{ $peso($shortfall) }}</td>
                </tr>
            </table>
        @endif

        {{-- Where the whole batch stands after this collection --}}
        <table class="totals" style="width: 100%; margin-top: 20px;">
            <tr>
                <td colspan="2" style="font-size: 8px; font-weight: 700; letter-spacing: 0.18em; text-transform: uppercase; color: #6B5044; padding-bottom: 4px;">
                    Batch {{ $data['number'] }} to date
                </td>
            </tr>
            <tr>
                <td>Units issued</td>
                <td class="num">{{ $totals['quantity_issued'] }} pcs</td>
            </tr>
            <tr>
                <td>Sold</td>
                <td class="num">{{ $totals['quantity_sold'] }} pcs · {{ $peso($totals['sold_value']) }}</td>
            </tr>
            <tr>
                <td>Returned in good condition</td>
                <td class="num">{{ $totals['quantity_returned'] }} pcs</td>
            </tr>
            <tr>
                <td>Expired / damaged / missing / other</td>
                <td class="num">{{ $totals['quantity_expired'] + $totals['quantity_damaged'] + $totals['quantity_missing'] }} pcs</td>
            </tr>
            <tr>
                <td>Still out</td>
                <td class="num">{{ $totals['outstanding'] }} pcs</td>
            </tr>
            <tr class="strong">
                <td>Total collected on this batch</td>
                <td class="num">{{ $peso($totals['amount_collected']) }}</td>
            </tr>
        </table>

        <table class="totals" style="width: 100%; margin-top: 8px;">
            <tr class="balance-row {{ $totals['amount_due'] > 0 ? 'balance-due' : 'balance-clear' }}">
                <td>Balance still to collect on this batch</td>
                <td class="num">{{ $peso($totals['amount_due']) }}</td>
            </tr>
        </table>

        <table class="stamp-row">
            <tr>
                <td class="stamp-meta">
                    Received from {{ $data['seller_name'] }}<br>
                    on {{ $settlement['settled_on'] }} via {{ $settlement['method_label'] }}.
                </td>
                <td style="text-align: right;">
                    <span class="stamp">{{ $stampLabel }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <div class="footer-msg">{{ $clean($options['footer']) }}</div>
        <div class="footer-meta">
            {{ collect([$brand['address'], $brand['phone'], $brand['facebook']])->filter()->implode('  ·  ') }}
        </div>
    </div>
</div>

@if (($autoPrint ?? false) && ! $isPdf)
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 350));</script>
@endif
</body>
</html>
