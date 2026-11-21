@php $m = fn ($v) => '₹' . number_format((float) $v, 2); @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Hisab — {{ $day->format('d M Y') }}</title>
    <style>
        body { font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif; color: #000; margin: 0; padding: 10px; width: 76mm; font-size: 13px; }
        h1 { font-size: 16px; margin: 0; text-align: center; }
        .c { text-align: center; } .sub { font-size: 11.5px; text-align: center; margin: 2px 0 8px; }
        .row { display: flex; justify-content: space-between; padding: 2px 0; }
        .b { font-weight: 800; } .big { font-size: 16px; font-weight: 900; }
        hr { border: 0; border-top: 1px dashed #000; margin: 7px 0; }
        table { width: 100%; border-collapse: collapse; font-size: 11.5px; } td, th { padding: 2px 0; text-align: left; } .r { text-align: right; }
        @media print { .noprint { display: none; } body { padding: 0; } @page { margin: 3mm; } }
    </style>
</head>
<body>
    <div class="noprint" style="margin-bottom:10px"><button onclick="window.print()" style="padding:8px 16px;font-weight:700">🖨️ Print</button></div>
    <h1>{{ $outlet->outlet_name ?? 'Swaad E Punjab' }}</h1>
    <div class="sub"><b>HISAB</b> · {{ $day->format('D, d M Y') }}<br>{{ $start->format('h:i A') }} → {{ $end->copy()->subMinute()->format('d M h:i A') }}</div>
    <hr>
    <div class="row"><span>💵 Cash</span><span class="b">{{ $m($totals['cash']) }}</span></div>
    <div class="row"><span>📱 UPI</span><span class="b">{{ $m($totals['upi']) }}</span></div>
    <div class="row"><span>💳 Card</span><span class="b">{{ $m($totals['card']) }}</span></div>
    <div class="row"><span>🌐 Online (website)</span><span class="b">{{ $m($totals['online']) }}</span></div>
    <hr>
    <div class="row big"><span>TOTAL ({{ $bills }} bills)</span><span>{{ $m($collected) }}</span></div>
    @if ($refunds > 0)<div class="row"><span>Refunded online</span><span>−{{ $m($refunds) }}</span></div>@endif
    @if ($unsettled->count())<div class="row b"><span>⚠ Unsettled ({{ $unsettled->count() }})</span><span>{{ $m($unsettled_amount) }}</span></div>@endif
    @if ($closing)
        <hr>
        <div class="c b">CASH DRAWER</div>
        <div class="row"><span>Opening cash</span><span>{{ $m($closing->opening_cash) }}</span></div>
        <div class="row"><span>+ Cash sales</span><span>{{ $m($totals['cash']) }}</span></div>
        <div class="row"><span>− Paid out</span><span>{{ $m($closing->expenses) }}</span></div>
        <div class="row b"><span>Should be</span><span>{{ $m($closing->expected_cash) }}</span></div>
        <div class="row b"><span>Counted</span><span>{{ $m($closing->counted_cash) }}</span></div>
        @php $d = (float) $closing->cash_difference; @endphp
        <div class="row big"><span>{{ $d == 0 ? 'MATCHED ✓' : ($d > 0 ? 'EXCESS' : 'SHORT') }}</span><span>{{ $d == 0 ? '' : $m(abs($d)) }}</span></div>
        @if ($closing->upi_counted !== null)<div class="row"><span>UPI app</span><span>{{ $m($closing->upi_counted) }} ({{ (float) $closing->upi_counted == $totals['upi'] ? 'ok' : 'diff ' . $m($closing->upi_counted - $totals['upi']) }})</span></div>@endif
        @if ($closing->card_counted !== null)<div class="row"><span>Card machine</span><span>{{ $m($closing->card_counted) }} ({{ (float) $closing->card_counted == $totals['card'] ? 'ok' : 'diff ' . $m($closing->card_counted - $totals['card']) }})</span></div>@endif
        @if ($closing->note)<div style="font-size:11.5px;margin-top:4px">Note: {{ $closing->note }}</div>@endif
        <div style="font-size:11.5px;margin-top:4px">Closed by {{ $closing->closed_by_name }} · {{ $closing->updated_at->timezone(config('app.timezone'))->format('d M h:i A') }}</div>
    @else
        <hr><div class="c b">Day not closed yet</div>
    @endif
    @if (! empty($staff))
        <hr>
        <table><thead><tr><th>By</th><th class="r">Bills</th><th class="r">Cash</th><th class="r">UPI</th><th class="r">Card</th></tr></thead><tbody>
            @foreach ($staff as $who => $r)
                <tr><td>{{ \Illuminate\Support\Str::limit($who, 14) }}</td><td class="r">{{ $r['bills'] }}</td><td class="r">{{ number_format($r['cash'], 0) }}</td><td class="r">{{ number_format($r['upi'], 0) }}</td><td class="r">{{ number_format($r['card'], 0) }}</td></tr>
            @endforeach
        </tbody></table>
    @endif
    <hr>
    <div class="sub">Printed {{ now()->format('d M Y, h:i A') }}<br><br>Cashier sign: ____________ &nbsp; Manager sign: ____________</div>
</body>
</html>
