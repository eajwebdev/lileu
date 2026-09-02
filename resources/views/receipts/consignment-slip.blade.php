@php
    /**
     * Consignment hand-over slip. The seller signs this when they take the
     * goods, so it doubles as the acknowledgement receipt for stock that has
     * left the shelf but is still ours.
     */
    $isPdf = $isPdf ?? false;
    $totals = $data['totals'];
    $stamp = $data['stamp'];

    $clean = fn (?string $text) => $isPdf
        ? trim(preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]/u', '', (string) $text))
        : (string) $text;

    $peso = fn ($value) => '₱' . number_format((float) $value, 2);

    $stampColors = ['green' => '#2F7D4A', 'muted-green' => '#3E8B5B', 'amber' => '#AC7526', 'muted-red' => '#9C111D'];
    $stampColor = $stampColors[$stamp['tone']] ?? '#AC7526';

    $logoData = $options['show_logo'] ? \App\Support\Settings::logoDataUri() : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Consignment {{ $data['number'] }} · {{ $clean($brand['name']) }}</title>
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
        .terms { font-size: 10px; color: #6B5044; line-height: 1.7; margin-top: 4px; }
        .terms strong { color: #3B2A22; }
        .sign { width: 100%; border-collapse: collapse; margin-top: 30px; }
        .sign td { width: 50%; padding-right: 20px; vertical-align: bottom; }
        .sign-line { border-top: 1px solid #8A6B5A; margin-top: 34px; padding-top: 5px; font-size: 9.5px; color: #8A6B5A; }
        .stamp-row { width: 100%; border-collapse: collapse; margin-top: 22px; }
        .stamp-row td { vertical-align: bottom; }
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
    <div class="no-print"><button type="button" onclick="window.print()">Print slip</button></div>
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
                                <div class="doc-type">Consignment Slip</div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="text-align: right;">
                    <div class="rcpt-label">Batch No.</div>
                    <div class="rcpt-number">{{ $data['number'] }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="body">
        <table class="info">
            <tr>
                <td>
                    <div class="info-label">Issued On</div>
                    <div class="info-value">{{ $data['issued_on'] }}</div>
                </td>
                <td>
                    <div class="info-label">Collect By</div>
                    <div class="info-value">{{ $data['due_on'] ?? 'On agreement' }}</div>
                </td>
                <td>
                    <div class="info-label">Seller</div>
                    <div class="info-value">{{ $data['seller_name'] }}</div>
                </td>
                <td>
                    <div class="info-label">Contact</div>
                    <div class="info-value">{{ $data['seller_phone'] }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="info-label">Seller Code</div>
                    <div class="info-value mono">{{ $data['seller_code'] }}</div>
                </td>
                <td>
                    <div class="info-label">Issued By</div>
                    <div class="info-value">{{ $data['issued_by'] ?? '—' }}</div>
                </td>
                @if ($data['notes'])
                    <td colspan="2">
                        <div class="info-label">Notes</div>
                        <div class="info-value">{{ $data['notes'] }}</div>
                    </td>
                @endif
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="ctr">Qty Out</th>
                    <th class="num">Remit Price</th>
                    <th class="num">Suggested Retail</th>
                    <th class="num">Seller Keeps</th>
                    <th class="num">Value Out</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['items'] as $item)
                    <tr>
                        <td class="item-name">{{ $item['name'] }}</td>
                        <td class="ctr">{{ $item['quantity_issued'] }}</td>
                        <td class="num">{{ $peso($item['unit_price']) }}</td>
                        <td class="num">{{ $peso($item['retail_price']) }}</td>
                        <td class="num">{{ $peso($item['margin'] * $item['quantity_issued']) }}</td>
                        <td class="num item-name">{{ $peso($item['unit_price'] * $item['quantity_issued']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="total-bar">
        <table>
            <tr>
                <td class="label">Total Value On Consignment · {{ $totals['quantity_issued'] }} pcs</td>
                <td class="amount">{{ $peso($totals['issued_value']) }}</td>
            </tr>
        </table>
    </div>

    <div class="body">
        <div class="terms">
            <strong>How this works.</strong>
            These goods remain the property of {{ $clean($brand['name']) }} until they are sold. The seller remits the
            <strong>remit price</strong> for every unit sold and keeps the difference from the retail price.
            Unsold stock is returned on or before the collect-by date. Units returned in good condition go back into
            inventory at no charge. Expired, damaged, missing or otherwise unusable units must be counted separately
            and explained during settlement.
        </div>

        <table class="sign">
            <tr>
                <td>
                    <div class="sign-line">Received by (seller) &mdash; signature over printed name</div>
                </td>
                <td>
                    <div class="sign-line">Released by ({{ $clean($brand['name']) }})</div>
                </td>
            </tr>
        </table>

        <table class="stamp-row">
            <tr>
                <td style="font-size: 9.5px; color: #8A6B5A;">
                    This slip records stock released on consignment.<br>
                    It is not a sales invoice and no payment is due at hand-over.
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
            {{ collect([$brand['address'], $brand['phone'], $brand['facebook']])->filter()->implode('  ·  ') }}
        </div>
    </div>
</div>

@if (($autoPrint ?? false) && ! $isPdf)
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 350));</script>
@endif
</body>
</html>
