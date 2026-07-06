<!-- Thermal Printer Receipt Container (Formatted for standard 80mm / 3.15in roll width) -->
<div style="background-color: white; padding: 16px; margin: 0 auto; border: 1px solid #EDE0C4; border-radius: 8px; font-family: monospace; font-size: 11px; color: black; max-width: 80mm;" id="bill-content-{{ $order->id }}">
    
    @php
        $receiptSettings = \App\Models\ReceiptSetting::getSettings();
    @endphp
    <!-- Receipt Header -->
    <div style="text-align: center; margin-bottom: 12px;">
        <h2 style="font-size: 16px; font-weight: bold; margin: 0; text-transform: uppercase; letter-spacing: 0.5px;">{{ $receiptSettings->outlet_name }}</h2>
        <p style="font-size: 10px; margin: 2px 0 0 0; white-space: pre-line;">{{ $receiptSettings->outlet_address }}</p>
        <p style="font-size: 10px; margin: 1px 0 0 0;">Contact: {{ $receiptSettings->outlet_phone }}</p>
        @if($receiptSettings->gst_number)
            <p style="font-size: 10px; margin: 2px 0 0 0; font-weight: bold; letter-spacing: 0.3px;">GSTIN: {{ $receiptSettings->gst_number }}</p>
        @endif
    </div>

    <!-- Divider -->
    <div style="border-top: 1px dashed black; margin: 8px 0;"></div>

    <!-- Order Metadata -->
    <div style="font-size: 10px; line-height: 1.4;">
        <div style="display: flex; justify-content: space-between;">
            <span>ORDER ID:</span>
            <span style="font-weight: bold;">{!! $order->highlighted_order_number_html !!}</span>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span>DATE:</span>
            <span>{{ $order->created_at->format('d/m/Y h:i A') }}</span>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span>CUST:</span>
            <span style="font-weight: bold; text-transform: uppercase;">{{ $order->customer_name }}</span>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span>PHONE:</span>
            <span>{{ $order->customer_phone }}</span>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span>PMT:</span>
            <span style="font-weight: bold; text-transform: uppercase;">{{ str_replace('_', ' ', $order->payment_method) }}</span>
        </div>
    </div>

    <!-- Divider -->
    <div style="border-top: 1px dashed black; margin: 8px 0;"></div>

    <!-- Delivery Address if applicable -->
    <div style="font-size: 10px; margin-bottom: 8px;">
        <span style="font-weight: bold;">DELIVER TO:</span>
        <p style="margin: 2px 0 0 0; line-height: 1.3;">{{ $order->delivery_address }}, {{ $order->city }}</p>
    </div>

    <!-- Divider -->
    <div style="border-top: 1px solid black; margin: 4px 0;"></div>

    <!-- Items Table -->
    <table style="width: 100%; font-size: 10px; border-collapse: collapse; margin: 6px 0;">
        <thead>
            <tr style="border-bottom: 1px solid black; font-weight: bold;">
                <th style="text-align: left; padding: 4px 0; width: 60%;">ITEM</th>
                <th style="text-align: center; padding: 4px 0; width: 15%;">QTY</th>
                <th style="text-align: right; padding: 4px 0; width: 25%;">AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr style="vertical-align: top; border-bottom: 1px dashed #eee;">
                    <td style="text-align: left; padding: 6px 0;">
                        <span style="font-weight: bold; display: block;">{{ $item->name }}</span>
                        <span style="font-size: 8px; color: #555; display: block; margin-top: 1px;">@ ₹{{ number_format($item->price, 0) }}</span>
                    </td>
                    <td style="text-align: center; padding: 6px 0;">{{ $item->quantity }}</td>
                    <td style="text-align: right; padding: 6px 0; font-weight: bold;">₹{{ number_format($item->subtotal, 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Divider -->
    <div style="border-top: 1px dashed black; margin: 8px 0;"></div>

    <!-- Totals -->
    <div style="font-size: 10px; font-weight: bold; line-height: 1.4;">
        <div style="display: flex; justify-content: space-between; font-weight: normal;">
            <span>SUB TOTAL:</span>
            <span>₹{{ number_format($order->subtotal, 0) }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; font-weight: normal;">
            <span>DELIVERY FEE:</span>
            <span>₹{{ number_format($order->delivery_fee, 0) }}</span>
        </div>
        <div style="border-top: 1px solid black; margin: 6px 0;"></div>
        <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: bold;">
            <span>TOTAL AMOUNT:</span>
            <span>₹{{ number_format($order->total, 0) }}</span>
        </div>
    </div>

    <!-- Divider -->
    <div style="border-top: 1px dashed black; margin: 8px 0;"></div>

    <!-- Special Instructions -->
    @if($order->special_instructions)
        <div style="font-size: 9px; background-color: #f9f9f9; padding: 6px; border: 1px dashed black; border-radius: 4px; margin-bottom: 8px;">
            <span style="font-weight: bold;">INSTRUCTIONS:</span>
            <p style="font-style: italic; margin: 2px 0 0 0; line-height: 1.3;">{{ $order->special_instructions }}</p>
        </div>
        <!-- Divider -->
        <div style="border-top: 1px dashed black; margin: 8px 0;"></div>
    @endif

    <!-- Receipt Footer -->
    <div style="text-align: center; font-size: 10px; line-height: 1.3; margin-top: 8px;">
        <p style="font-weight: bold;">THANK YOU FOR YOUR ORDER!</p>
        @if($receiptSettings->footer_message)
            <p>{{ $receiptSettings->footer_message }}</p>
        @endif
        <p style="font-size: 8px; color: #999; margin-top: 4px;">Order processed via Web System</p>
    </div>

    <!-- Print Button (Hidden during print) -->
    <div style="display: flex; justify-content: center; margin-top: 16px; padding-top: 12px; border-top: 1px solid #eee;" class="print-btn-container">
        <button 
            type="button" 
            onclick="printThermalReceipt('bill-content-{{ $order->id }}', '{{ $order->order_number }}')" 
            style="padding: 6px 12px; background-color: black; color: white; font-weight: bold; border-radius: 4px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; border: none; cursor: pointer; display: flex; align-items: center; gap: 6px;"
        >
            <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            Print Thermal Receipt
        </button>
    </div>

    <!-- Inline Print Script customized for 80mm thermal width -->
    <script>
        if (typeof window.printThermalReceipt === 'undefined') {
            window.printThermalReceipt = function(divId, orderNum) {
                const printContents = document.getElementById(divId).innerHTML;
                const printWindow = window.open('', '_blank', 'height=600,width=400');
                
                printWindow.document.write('<html><head><title>Thermal Receipt - ' + orderNum + '<\/title>');
                printWindow.document.write('<style>');
                printWindow.document.write('@page { size: 80mm auto; margin: 0; }');
                printWindow.document.write('@media print { body { margin: 0; padding: 4mm; width: 72mm; } .print-btn-container { display: none !important; } }');
                printWindow.document.write('body { font-family: "Courier New", Courier, monospace; background: white; color: black; font-size: 10px; -webkit-print-color-adjust: exact; }');
                printWindow.document.write('<\/style>');
                printWindow.document.write('<\/head><body>');
                printWindow.document.write('<div style="width: 72mm; max-width: 72mm; word-wrap: break-word;">');
                printWindow.document.write(printContents);
                printWindow.document.write('<\/div>');
                printWindow.document.write('<\/body><\/html>');
                printWindow.document.close();
                
                setTimeout(function() {
                    printWindow.focus();
                    printWindow.print();
                    printWindow.close();
                }, 500);
            };
        }
    </script>
</div>
