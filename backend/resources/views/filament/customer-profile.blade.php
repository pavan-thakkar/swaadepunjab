@php
    $statusColor = fn ($st) => match ($st) {
        'delivered' => '#11622F', 'cancelled' => '#B91C1C', 'awaiting_payment' => '#B45309', default => '#1D4ED8',
    };
    $phone10 = substr(preg_replace('/\D/', '', (string) $c->customer_phone), -10);
@endphp
<div style="display:flex;flex-direction:column;gap:16px;font-size:14px">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px">
        @foreach ([
            ['Orders', $s['count'] . ($s['cancelled'] ? ' (+' . $s['cancelled'] . ' cancelled)' : '')],
            ['Total spent', '₹' . number_format($s['spent'], 0)],
            ['Avg / order', '₹' . number_format($s['avg'], 0)],
            ['Last order', $s['last'] ? $s['last']->diffForHumans() : '—'],
            ['Customer since', $s['first'] ? $s['first']->format('d M Y') : '—'],
            ['Loyalty points', $s['points']],
        ] as [$label, $value])
            <div style="border:1px solid rgba(0,0,0,.08);border-radius:12px;padding:10px 12px;background:rgba(230,168,23,.06)">
                <div style="font-size:11px;font-weight:700;color:#8a7a5c;text-transform:uppercase;letter-spacing:.04em">{{ $label }}</div>
                <div style="font-size:17px;font-weight:800;margin-top:2px">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    @if ($c->delivery_address && ! in_array($c->delivery_address, ['Dine In', 'Take Away', 'Take Away (counter)'], true))
        <div><strong>Address:</strong> {{ $c->delivery_address }}{{ $c->city ? ', ' . $c->city : '' }}</div>
    @endif

    @if ($s['favourites']->isNotEmpty())
        <div>
            <strong>Favourite dishes:</strong>
            @foreach ($s['favourites'] as $f)
                <span style="display:inline-block;margin:3px 4px 0 0;padding:3px 10px;border-radius:99px;background:#FFF3D6;font-weight:600">{{ $f->name }} × {{ (int) $f->qty }}</span>
            @endforeach
        </div>
    @endif

    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="tel:+91{{ $phone10 }}" style="padding:8px 14px;border-radius:10px;background:#1D4ED8;color:#fff;font-weight:700;text-decoration:none">📞 Call</a>
        <a href="https://wa.me/91{{ $phone10 }}" target="_blank" rel="noopener" style="padding:8px 14px;border-radius:10px;background:#16A34A;color:#fff;font-weight:700;text-decoration:none">💬 WhatsApp</a>
    </div>

    <div>
        <strong>Recent orders</strong>
        <table style="width:100%;border-collapse:collapse;margin-top:6px;font-size:13px">
            <thead>
                <tr style="text-align:left;color:#8a7a5c;border-bottom:1px solid rgba(0,0,0,.1)">
                    <th style="padding:6px 4px">Date</th><th style="padding:6px 4px">Order</th><th style="padding:6px 4px">Items</th>
                    <th style="padding:6px 4px;text-align:right">Amount</th><th style="padding:6px 4px">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($s['orders']->take(10) as $o)
                    <tr style="border-bottom:1px solid rgba(0,0,0,.05)">
                        <td style="padding:6px 4px;white-space:nowrap">{{ $o->created_at?->format('d M, h:i A') }}</td>
                        <td style="padding:6px 4px"><a href="{{ \App\Filament\Resources\OrderResource::getUrl('view', ['record' => $o]) }}" style="color:#B45309;font-weight:700">#{{ substr($o->order_number, -6) }}</a></td>
                        <td style="padding:6px 4px">{{ \Illuminate\Support\Str::limit($o->items->map(fn ($i) => $i->quantity . '× ' . $i->name)->implode(', '), 60) }}</td>
                        <td style="padding:6px 4px;text-align:right;font-weight:700">₹{{ number_format((float) $o->total, 0) }}</td>
                        <td style="padding:6px 4px;color:{{ $statusColor($o->status) }};font-weight:700">{{ ucwords(str_replace('_', ' ', $o->status)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if ($s['orders']->count() > 10)
            <div style="color:#8a7a5c;margin-top:6px">Showing the latest 10 of {{ $s['orders']->count() }} orders.</div>
        @endif
    </div>
</div>
