<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Print {{ $order->order_number }}</title>
    <style>
        body { margin: 0; background: #fff; }
        .print-btn-container { display: none !important; }
        .page-break { page-break-after: always; break-after: page; height: 0; }
        .toolbar { display: flex; gap: 8px; justify-content: center; padding: 10px; font-family: sans-serif; }
        .toolbar button { height: 40px; padding: 0 18px; border-radius: 10px; border: 0; background: #E6A817; font-weight: 800; cursor: pointer; }
        @media print { .toolbar { display: none; } @page { margin: 4mm; } }
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Print</button><button type="button" style="background:#eee" onclick="window.close()">Close</button></div>
    @if (in_array($type, ['bill', 'both'], true))
        @include('admin.order-bill', ['order' => $order])
    @endif
    @if ($type === 'both')<div class="page-break"></div>@endif
    @if (in_array($type, ['kot', 'both'], true))
        @include('admin.kitchen-ticket', ['order' => $order])
    @endif
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
