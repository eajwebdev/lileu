@php
    /**
     * Reseller receipt — print + PDF.
     *
     * Deliberately table-based with inline-ish CSS: dompdf has no flexbox or
     * grid, and the browser print sheet must match the PDF exactly. The
     * composition mirrors resources/js/Components/Lileu/Receipt.jsx.
     */
    $isPdf = $isPdf ?? false;
    $paper = $paper ?? 'a4';
    $thermal = $paper === 'thermal';

    $brand = $data['brand'];
    $options = $data['options'];
    $order = $data['order'];
    $reseller = $data['reseller'];
    $items = $data['items'];
    $totals = $data['totals'];
    $payments = $data['payments'];
    $receipt = $data['receipt'];
    $stamp = $data['stamp'];

    // dompdf's core fonts have no emoji coverage; drop astral-plane glyphs.
    $clean = fn (?string $text) => $isPdf
        ? trim(preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]/u', '', (string) $text))
        : (string) $text;

    $peso = fn ($value) => '₱' . number_format((float) $value, 2);

    $stampColors = [
        'green' => '#2F7D4A',
        'muted-green' => '#3E8B5B',
        'amber' => '#AC7526',
        'muted-red' => '#9C111D',
    ];
    $stampColor = $stampColors[$stamp['tone']] ?? '#AC7526';

    $logoData = $options['show_logo'] ? \App\Support\Settings::logoDataUri() : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $receipt['number'] }} · {{ $clean($brand['name']) }}</title>
    <style>
        @page { margin: {{ $thermal ? '4mm' : '12mm' }}; size: {{ $thermal ? '80mm auto' : 'A4' }}; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            font-family: {{ $isPdf ? "'DejaVu Sans', sans-serif" : "'Plus Jakarta Sans', 'Segoe UI', system-ui, sans-serif" }};
            font-size: {{ $thermal ? '10px' : '12px' }};
            color: #3B2A22;
            background: #F5E8D9;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .sheet {
            width: 100%;
            max-width: {{ $thermal ? '72mm' : '190mm' }};
            margin: 0 auto;
            background: #F5E8D9;
        }

        /* 1 — Chocolate header */
        .header { background: #3B2A22; color: #FBF5EC; padding: {{ $thermal ? '10px' : '20px 26px' }}; }
        .header table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .brand-name { font-size: {{ $thermal ? '14px' : '21px' }}; font-weight: 700; letter-spacing: -0.01em; }
        .brand-tagline { font-size: 9px; letter-spacing: 0.18em; text-transform: uppercase; color: #EFAFB8; margin-top: 2px; }
        .doc-type { font-size: 9px; letter-spacing: 0.22em; text-transform: uppercase; color: rgba(251,245,236,0.7); margin-top: 8px; font-weight: 700; }
        .rcpt-label { font-size: 8px; letter-spacing: 0.16em; text-transform: uppercase; color: #EFAFB8; font-weight: 700; }
        .rcpt-number { font-family: 'DejaVu Sans Mono', 'Courier New', monospace; font-size: {{ $thermal ? '11px' : '15px' }}; font-weight: 700; }
        .rcpt-order { font-size: 9px; color: rgba(251,245,236,0.6); margin-top: 6px; }
        .logo { width: {{ $thermal ? '26px' : '44px' }}; height: auto; }

        /* 2 — Cream body */
        .body { padding: {{ $thermal ? '10px' : '20px 26px' }}; background: #F5E8D9; }

        /* 3 — Info grid */
        .info { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .info td { padding: 0 10px 12px 0; vertical-align: top; width: 25%; }
        .info-label { font-size: 8px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: #8A6B5A; }
        .info-value { font-size: {{ $thermal ? '10px' : '12px' }}; font-weight: 600; color: #3B2A22; margin-top: 2px; }
        .mono { font-family: 'DejaVu Sans Mono', 'Courier New', monospace; }

        /* 4 — Item table */
        .items { width: 100%; border-collapse: collapse; }
        .items thead th {
            font-size: 8px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase;
            color: #523C33; padding-bottom: 6px; border-bottom: 2px solid #3B2A22; text-align: left;
        }
        .items tbody td { padding: 7px 0; border-bottom: 1px solid rgba(59,42,34,0.16); font-size: {{ $thermal ? '10px' : '12px' }}; }
        .num { text-align: right; }
        .ctr { text-align: center; }
        .item-name { font-weight: 600; color: #3B2A22; }

        /* 5 — Breakdown */
        .totals { width: {{ $thermal ? '100%' : '52%' }}; border-collapse: collapse; margin-top: 16px; margin-left: auto; }
        .totals td { padding: 3px 0; font-size: {{ $thermal ? '10px' : '12px' }}; color: #6B5044; }
        .totals td.num { text-align: right; }
        .totals tr.strong td { font-weight: 700; color: #3B2A22; }
        .totals tr.paid td { font-weight: 700; color: #2F7D4A; }

        .balance-row td { padding: 8px 10px; font-weight: 700; font-size: {{ $thermal ? '11px' : '13px' }}; }
        .balance-due td { background: rgba(215,151,62,0.2); color: #AC7526; }
        .balance-clear td { background: #E4F1E8; color: #2F7D4A; }

        /* 6 — Dark total bar */
        .total-bar { background: #2B1B16; color: #FBF5EC; padding: {{ $thermal ? '8px 10px' : '14px 26px' }}; }
        .total-bar table { width: 100%; border-collapse: collapse; }
        .total-bar .label { font-size: 9px; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; color: #EFAFB8; }
        .total-bar .amount { text-align: right; font-size: {{ $thermal ? '15px' : '24px' }}; font-weight: 700; }

        /* Payment history */
        .history-title { font-size: 8px; font-weight: 700; letter-spacing: 0.18em; text-transform: uppercase; color: #6B5044; margin: 22px 0 6px; }
        .history { width: 100%; border-collapse: collapse; font-size: {{ $thermal ? '9px' : '10.5px' }}; }
        .history thead th { text-align: left; font-size: 8px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #8A6B5A; padding-bottom: 4px; border-bottom: 1px solid rgba(59,42,34,0.2); }
        .history tbody td { padding: 5px 0; border-bottom: 1px solid rgba(59,42,34,0.1); color: #523C33; }
        .history tfoot td { padding-top: 6px; font-weight: 700; color: #3B2A22; }

        /* 7 — Stamp */
        .stamp-row { width: 100%; border-collapse: collapse; margin-top: 24px; }
        .stamp-row td { vertical-align: bottom; }
        .stamp-meta { font-size: 9.5px; color: #8A6B5A; line-height: 1.6; }
        .stamp {
            display: inline-block; border: 3px solid {{ $stampColor }}; color: {{ $stampColor }};
            padding: {{ $thermal ? '5px 10px' : '9px 18px' }}; border-radius: 10px;
            font-size: {{ $thermal ? '11px' : '15px' }}; font-weight: 700;
            letter-spacing: 0.08em; text-transform: uppercase; white-space: nowrap;
        }

        /* 8 — Footer */
        .footer { border-top: 1px solid #EBD8C3; background: #FDFAF5; padding: {{ $thermal ? '10px' : '14px 26px' }}; text-align: center; }
        .footer-msg { font-size: {{ $thermal ? '10px' : '13px' }}; font-weight: 700; color: #3B2A22; }
        .footer-meta { font-size: 9px; color: #8A6B5A; margin-top: 4px; }

        .no-print { text-align: center; padding: 14px; }
        .no-print button {
            font: inherit; font-weight: 600; cursor: pointer; border: 0; border-radius: 10px;
            background: #3B2A22; color: #FBF5EC; padding: 9px 18px; margin: 0 4px;
        }
        .no-print a { font: inherit; font-weight: 600; text-decoration: none; color: #3B2A22; padding: 9px 18px; }

        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
@unless ($isPdf)
    <div class="no-print">
        <button type="button" onclick="window.print()">Print receipt</button>
        <a href="{{ route('receipts.pdf', $receipt['number']) }}">Download PDF</a>
        <a href="{{ route('receipts.print', ['payment' => $receipt['number'], 'paper' => $thermal ? 'a4' : 'thermal']) }}">
            Switch to {{ $thermal ? 'A4' : '80mm' }}
        </a>
    </div>
@endunless

<div class="sheet">
    {{-- 1 — Chocolate header --}}
    <div class="header">
        <table>
            <tr>
                <td>
                    <table>
                        <tr>
                            @if ($logoData)
                                <td style="width: {{ $thermal ? '32px' : '54px' }};">
                                    <img class="logo" src="{{ $logoData }}" alt="">
                                </td>
                            @endif
                            <td>
                                <div class="brand-name">{{ $clean($brand['name']) }}</div>
                                <div class="brand-tagline">{{ $clean($brand['tagline']) }}</div>
                                <div class="doc-type">Official Receipt</div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="text-align: right;">
                    <div class="rcpt-label">Receipt No.</div>
                    <div class="rcpt-number">{{ $receipt['number'] }}</div>
                    <div class="rcpt-order">Order {{ $order['number'] }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- 2/3 — Cream body with order information --}}
    <div class="body">
        <table class="info">
            <tr>
                <td>
                    <div class="info-label">Order No.</div>
                    <div class="info-value mono">{{ $order['number'] }}</div>
                </td>
                <td>
                    <div class="info-label">Order Date</div>
                    <div class="info-value">{{ $order['ordered_date'] }}</div>
                </td>
                <td>
                    <div class="info-label">Date Needed</div>
                    <div class="info-value">{{ $order['date_needed'] ?? '—' }}</div>
                </td>
                <td>
                    <div class="info-label">Time</div>
                    <div class="info-value">{{ $order['time_needed'] ?? $order['ordered_time'] }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="info-label">Reseller</div>
                    <div class="info-value">{{ $reseller['business_name'] ?: $reseller['name'] }}</div>
                </td>
                <td>
                    <div class="info-label">Contact</div>
                    <div class="info-value">{{ $reseller['phone'] }}</div>
                </td>
                <td>
                    <div class="info-label">Fulfillment</div>
                    <div class="info-value">{{ $order['fulfillment_type'] === 'delivery' ? 'Delivery' : 'Pickup' }}</div>
                </td>
                <td>
                    <div class="info-label">Payment</div>
                    <div class="info-value">{{ $receipt['method_label'] }}</div>
                </td>
            </tr>
            @if ($order['delivery_address'])
                <tr>
                    <td colspan="4">
                        <div class="info-label">Deliver To</div>
                        <div class="info-value">{{ $order['delivery_address'] }}</div>
                    </td>
                </tr>
            @endif
        </table>

        {{-- 4 — Item table --}}
        <table class="items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="ctr">Qty</th>
                    <th class="num">Unit Price</th>
                    <th class="num">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td class="item-name">{{ $item['name'] }}</td>
                        <td class="ctr">{{ $item['quantity'] }}</td>
                        <td class="num">{{ $peso($item['unit_price']) }}</td>
                        <td class="num item-name">{{ $peso($item['line_total']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- 5 — Payment breakdown --}}
        <table class="totals">
            <tr>
                <td>Subtotal</td>
                <td class="num">{{ $peso($totals['subtotal']) }}</td>
            </tr>
            <tr>
                <td>Discount</td>
                <td class="num">{{ $totals['discount'] > 0 ? '−' : '' }}{{ $peso($totals['discount']) }}</td>
            </tr>
            <tr>
                <td>Delivery Fee</td>
                <td class="num">{{ $peso($totals['delivery_fee']) }}</td>
            </tr>
        </table>
    </div>

    {{-- 6 — Dark chocolate total bar --}}
    <div class="total-bar">
        <table>
            <tr>
                <td class="label">Order Total</td>
                <td class="amount">{{ $peso($totals['total']) }}</td>
            </tr>
        </table>
    </div>

    <div class="body">
        <table class="totals" style="margin-top: 0; width: 100%;">
            <tr>
                <td>Required Downpayment – {{ $totals['downpayment_percent'] }}%</td>
                <td class="num">{{ $peso($totals['downpayment_required']) }}</td>
            </tr>
            <tr class="strong">
                <td>Amount Paid (this receipt)</td>
                <td class="num">{{ $peso($receipt['amount']) }}</td>
            </tr>
            <tr class="paid">
                <td>Total Paid on Order</td>
                <td class="num">{{ $peso($totals['amount_paid']) }}</td>
            </tr>
        </table>

        <table class="totals" style="width: 100%; margin-top: 8px;">
            <tr class="balance-row {{ $totals['balance'] > 0 ? 'balance-due' : 'balance-clear' }}">
                <td>Remaining Balance</td>
                <td class="num">{{ $peso($totals['balance']) }}</td>
            </tr>
        </table>

        {{-- Payment history --}}
        @if (count($payments) > 0)
            <div class="history-title">Payment History</div>
            <table class="history">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Receipt</th>
                        <th>Method</th>
                        <th>Type</th>
                        <th class="num">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payments as $entry)
                        <tr>
                            <td>{{ $entry['date'] }} {{ $entry['time'] }}</td>
                            <td class="mono">{{ $entry['receipt_number'] }}</td>
                            <td>{{ $entry['method_label'] }}</td>
                            <td>{{ $entry['kind_label'] }}</td>
                            <td class="num">{{ $peso($entry['amount']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="num">Total Paid</td>
                        <td class="num">{{ $peso($totals['amount_paid']) }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif

        {{-- 7 — Payment status stamp --}}
        <table class="stamp-row">
            <tr>
                <td class="stamp-meta">
                    @if ($receipt['reference'])
                        Payment reference: <span class="mono">{{ $receipt['reference'] }}</span><br>
                    @endif
                    @if ($receipt['paid_date'])
                        Paid on {{ $receipt['paid_date'] }} at {{ $receipt['paid_time'] }}<br>
                    @endif
                    @if ($order['notes'])
                        Note: {{ $order['notes'] }}
                    @endif
                </td>
                <td style="text-align: right;">
                    <span class="stamp">{{ $stamp['label'] }}</span>
                </td>
            </tr>
        </table>
    </div>

    {{-- 8 — Footer --}}
    <div class="footer">
        <div class="footer-msg">{{ $clean($options['footer']) }}</div>
        <div class="footer-meta">
            {{ collect([$brand['facebook'], $brand['phone'], $brand['website']])->filter()->implode('  ·  ') }}
        </div>
    </div>
</div>

@if (($autoPrint ?? false) && ! $isPdf)
    <script>
        window.addEventListener('load', () => setTimeout(() => window.print(), 350));
    </script>
@endif
</body>
</html>
