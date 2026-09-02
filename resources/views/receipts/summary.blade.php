@php
    /** Consolidated order payment summary — every payment against one order. */
    $isPdf = $isPdf ?? false;

    $brand = $data['brand'];
    $options = $data['options'];
    $order = $data['order'];
    $reseller = $data['reseller'];
    $items = $data['items'];
    $totals = $data['totals'];
    $payments = $data['payments'];
    $stamp = $data['stamp'];

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
    <title>Payment summary {{ $order['number'] }} · {{ $clean($brand['name']) }}</title>
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
        .logo { width: 44px; height: auto; }
        .body { padding: 20px 26px; background: #F5E8D9; }
        .info { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .info td { padding: 0 10px 12px 0; vertical-align: top; width: 25%; }
        .info-label { font-size: 8px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: #8A6B5A; }
        .info-value { font-size: 12px; font-weight: 600; margin-top: 2px; }
        .mono { font-family: 'DejaVu Sans Mono', 'Courier New', monospace; }
        .items { width: 100%; border-collapse: collapse; }
        .items thead th { font-size: 8px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: #523C33; padding-bottom: 6px; border-bottom: 2px solid #3B2A22; text-align: left; }
        .items tbody td { padding: 7px 0; border-bottom: 1px solid rgba(59,42,34,0.16); }
        .num { text-align: right; }
        .ctr { text-align: center; }
        .item-name { font-weight: 600; }
        .total-bar { background: #2B1B16; color: #FBF5EC; padding: 14px 26px; }
        .total-bar table { width: 100%; border-collapse: collapse; }
        .total-bar .label { font-size: 9px; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; color: #EFAFB8; }
        .total-bar .amount { text-align: right; font-size: 24px; font-weight: 700; }
        .history-title { font-size: 8px; font-weight: 700; letter-spacing: 0.18em; text-transform: uppercase; color: #6B5044; margin: 0 0 6px; }
        .history { width: 100%; border-collapse: collapse; font-size: 10.5px; }
        .history thead th { text-align: left; font-size: 8px; font-weight: 700; text-transform: uppercase; color: #8A6B5A; padding-bottom: 4px; border-bottom: 1px solid rgba(59,42,34,0.2); }
        .history tbody td { padding: 6px 0; border-bottom: 1px solid rgba(59,42,34,0.1); color: #523C33; }
        .history tfoot td { padding-top: 7px; font-weight: 700; font-size: 12px; }
        .totals { width: 52%; border-collapse: collapse; margin-top: 16px; margin-left: auto; }
        .totals td { padding: 3px 0; color: #6B5044; }
        .totals tr.paid td { font-weight: 700; color: #2F7D4A; }
        .balance-row td { padding: 8px 10px; font-weight: 700; font-size: 13px; }
        .balance-due td { background: rgba(215,151,62,0.2); color: #AC7526; }
        .balance-clear td { background: #E4F1E8; color: #2F7D4A; }
        .stamp-row { width: 100%; border-collapse: collapse; margin-top: 24px; }
        .stamp-row td { vertical-align: bottom; }
        .stamp-meta { font-size: 9.5px; color: #8A6B5A; line-height: 1.6; }
        .stamp { display: inline-block; border: 3px solid {{ $stampColor }}; color: {{ $stampColor }}; padding: 9px 18px; border-radius: 10px; font-size: 15px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; white-space: nowrap; }
        .footer { border-top: 1px solid #EBD8C3; background: #FDFAF5; padding: 14px 26px; text-align: center; }
        .footer-msg { font-size: 13px; font-weight: 700; }
        .footer-meta { font-size: 9px; color: #8A6B5A; margin-top: 4px; }
        @media print { body { background: #fff; } }
    </style>
</head>
<body>
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
                                <div class="doc-type">Order Payment Summary</div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="text-align: right;">
                    <div class="rcpt-label">Order No.</div>
                    <div class="rcpt-number">{{ $order['number'] }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="body">
        <table class="info">
            <tr>
                <td>
                    <div class="info-label">Order Date</div>
                    <div class="info-value">{{ $order['ordered_date'] }}</div>
                </td>
                <td>
                    <div class="info-label">Date Needed</div>
                    <div class="info-value">{{ $order['date_needed'] ?? '—' }}</div>
                </td>
                <td>
                    <div class="info-label">Reseller</div>
                    <div class="info-value">{{ $reseller['business_name'] ?: $reseller['name'] }}</div>
                </td>
                <td>
                    <div class="info-label">Contact</div>
                    <div class="info-value">{{ $reseller['phone'] }}</div>
                </td>
            </tr>
        </table>

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
    </div>

    <div class="total-bar">
        <table>
            <tr>
                <td class="label">Order Total</td>
                <td class="amount">{{ $peso($totals['total']) }}</td>
            </tr>
        </table>
    </div>

    <div class="body">
        <div class="history-title">All payments against this order</div>
        <table class="history">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Receipt No.</th>
                    <th>Method</th>
                    <th>Type</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $i => $entry)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $entry['date'] }} {{ $entry['time'] }}</td>
                        <td class="mono">{{ $entry['receipt_number'] }}</td>
                        <td>{{ $entry['method_label'] }}</td>
                        <td>{{ $entry['kind_label'] }}</td>
                        <td class="num">{{ $peso($entry['amount']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding: 14px 0; color: #8A6B5A;">
                            No payments have been recorded against this order yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="num">Total Paid</td>
                    <td class="num">{{ $peso($totals['amount_paid']) }}</td>
                </tr>
            </tfoot>
        </table>

        <table class="totals" style="width: 100%;">
            <tr>
                <td>Order Total</td>
                <td class="num">{{ $peso($totals['total']) }}</td>
            </tr>
            <tr class="paid">
                <td>Total Paid</td>
                <td class="num">{{ $peso($totals['amount_paid']) }}</td>
            </tr>
        </table>

        <table class="totals" style="width: 100%; margin-top: 8px;">
            <tr class="balance-row {{ $totals['balance'] > 0 ? 'balance-due' : 'balance-clear' }}">
                <td>Remaining Balance</td>
                <td class="num">{{ $peso($totals['balance']) }}</td>
            </tr>
        </table>

        <table class="stamp-row">
            <tr>
                <td class="stamp-meta">
                    This summary is a representation of payments already recorded.<br>
                    It does not create a new transaction.
                </td>
                <td style="text-align: right;">
                    <span class="stamp">{{ $stamp['label'] }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <div class="footer-msg">{{ $clean($options['footer']) }}</div>
        <div class="footer-meta">
            {{ collect([$brand['facebook'], $brand['phone'], $brand['website']])->filter()->implode('  ·  ') }}
        </div>
    </div>
</div>
</body>
</html>
