<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Plan deliveries — {{ $day->format('d M Y') }}</title>
    <style>
        body { font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif; color: #111; margin: 24px; }
        h1 { font-size: 20px; margin: 0; }
        .sub { color: #555; font-size: 13px; margin: 4px 0 16px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border: 1px solid #bbb; padding: 7px 8px; text-align: left; vertical-align: top; }
        th { background: #f3f3f3; }
        .time { font-weight: 800; white-space: nowrap; }
        .meals { font-weight: 900; font-size: 16px; text-align: center; }
        .tick { width: 60px; }
        tfoot td { font-weight: 900; }
        @media print { body { margin: 8mm; } .noprint { display: none; } }
    </style>
</head>
<body>
    <div class="noprint" style="margin-bottom:12px"><button onclick="window.print()" style="padding:8px 16px;font-weight:700">🖨️ Print</button></div>
    <h1>{{ $outlet->outlet_name ?? 'Swaad E Punjab' }} — Monthly plan deliveries</h1>
    <div class="sub">{{ $day->format('l, d M Y') }} · {{ $plans->count() }} customers · printed {{ now()->format('d M, h:i A') }}</div>

    @if ($plans->isEmpty())
        <p>No plan deliveries on this day.</p>
    @else
        @php $total = 0; @endphp
        <table>
            <thead>
                <tr><th>Time</th><th>Customer / Company</th><th>Phone</th><th>Meals</th><th>Plan</th><th>Address &amp; note</th><th class="tick">Done ✓</th></tr>
            </thead>
            <tbody>
                @foreach ($plans as $p)
                    @php
                        $m = $marks->get($p->id);
                        $meals = $m && $m->status === 'skipped' ? 0 : ($m && $m->people !== null ? $m->people : (int) ($p->agreed_people ?: $p->people ?: 1));
                        $total += $meals;
                    @endphp
                    <tr>
                        <td class="time">{{ $p->delivery_time ?: '—' }}</td>
                        <td><strong>{{ $p->company ?: $p->name }}</strong>@if ($p->company)<br>{{ $p->name }}@endif</td>
                        <td>{{ $p->phone }}</td>
                        <td class="meals">{{ $m && $m->status === 'skipped' ? 'SKIP' : $meals }}</td>
                        <td>{{ $p->plan_name ?: $p->plan?->name }}{{ $p->plan?->meal_type ? ' · ' . $p->plan->meal_type : '' }}</td>
                        <td>{{ $p->address }}@if ($p->customer_note)<br><em>{{ $p->customer_note }}</em>@endif</td>
                        <td>{{ $m && $m->status === 'delivered' ? '✓' : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot><tr><td colspan="3">Total meals</td><td class="meals">{{ $total }}</td><td colspan="3"></td></tr></tfoot>
        </table>
    @endif
</body>
</html>
