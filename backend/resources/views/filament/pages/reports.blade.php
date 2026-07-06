<x-filament-panels::page>
    <div class="space-y-6">
        
        <!-- Native Filament Styled Filter Card -->
        <div class="fi-section p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm">
            <form method="GET" class="flex flex-wrap items-end gap-6">
                <!-- Start Date -->
                <div class="flex-1 min-w-[200px] space-y-1.5">
                    <label for="startDate" class="block text-xs font-bold text-gray-500 uppercase tracking-wider dark:text-gray-400">Start Date</label>
                    <x-filament::input.wrapper>
                        <x-filament::input 
                            type="date" 
                            id="startDate" 
                            name="startDate" 
                            value="{{ $startDate }}" 
                            class="w-full"
                        />
                    </x-filament::input.wrapper>
                </div>
                
                <!-- End Date -->
                <div class="flex-1 min-w-[200px] space-y-1.5">
                    <label for="endDate" class="block text-xs font-bold text-gray-500 uppercase tracking-wider dark:text-gray-400">End Date</label>
                    <x-filament::input.wrapper>
                        <x-filament::input 
                            type="date" 
                            id="endDate" 
                            name="endDate" 
                            value="{{ $endDate }}" 
                            class="w-full"
                        />
                    </x-filament::input.wrapper>
                </div>
                
                <!-- Actions -->
                <div class="flex items-center gap-3">
                    <x-filament::button type="submit" color="primary">
                        Apply Filter
                    </x-filament::button>
                    <x-filament::button tag="a" href="{{ request()->url() }}" color="gray">
                        Reset
                    </x-filament::button>
                </div>
            </form>
        </div>

        <!-- KPI Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            
            <!-- Today's Revenue -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Today's Sales</p>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">₹{{ number_format($todayRevenue, 2) }}</h3>
                    <p class="text-[10px] text-emerald-600 dark:text-emerald-400 mt-1 font-semibold">⚡ {{ $todayOrders }} orders today</p>
                </div>
                <div class="p-3 bg-amber-50 dark:bg-amber-950/30 rounded-lg text-amber-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>

            <!-- Range Revenue -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Range Sales</p>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">₹{{ number_format($totalRevenue, 2) }}</h3>
                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-1">Excludes cancelled orders</p>
                </div>
                <div class="p-3 bg-emerald-50 dark:bg-emerald-950/30 rounded-lg text-emerald-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
            </div>

            <!-- Range Orders Count -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Range Orders</p>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $totalOrders }}</h3>
                    <p class="text-[10px] text-blue-600 dark:text-blue-400 mt-1 font-semibold">🎉 {{ $deliveredOrders }} delivered</p>
                </div>
                <div class="p-3 bg-blue-50 dark:bg-blue-950/30 rounded-lg text-blue-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </div>
            </div>

            <!-- Average Order Value -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Avg. Order Value</p>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">₹{{ number_format($averageOrderValue, 2) }}</h3>
                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-1">Per transaction average</p>
                </div>
                <div class="p-3 bg-indigo-50 dark:bg-indigo-950/30 rounded-lg text-indigo-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                </div>
            </div>

        </div>

        <!-- Charts / Detailed Lists Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Top Selling Items -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm">
                <h4 class="text-base font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-1.5">
                    🔥 Top Selling Menu Items
                </h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 text-gray-400 font-semibold uppercase">
                                <th class="pb-3 text-left">Food Item</th>
                                <th class="pb-3 text-center w-20">Qty Sold</th>
                                <th class="pb-3 text-right w-28">Total Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-850">
                            @forelse($topItems as $item)
                                <tr>
                                    <td class="py-3 font-semibold text-gray-855 dark:text-gray-150">{{ $item->name }}</td>
                                    <td class="py-3 text-center font-bold text-gray-600 dark:text-gray-400">{{ $item->total_qty }}</td>
                                    <td class="py-3 text-right font-black text-emerald-600 dark:text-emerald-400">₹{{ number_format($item->total_revenue, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-4 text-center text-gray-400 italic">No sales recorded in this range.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Order Status Breakdown -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm">
                <h4 class="text-base font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-1.5">
                    📊 Order Status & Fulfilment
                </h4>
                <div class="space-y-4">
                    @php
                        $statuses = [
                            'pending'          => ['name' => 'Pending Orders', 'color' => 'bg-amber-500', 'text' => 'text-amber-500'],
                            'accepted'         => ['name' => 'Accepted Orders', 'color' => 'bg-blue-500', 'text' => 'text-blue-500'],
                            'preparing'        => ['name' => 'Preparing Orders', 'color' => 'bg-purple-500', 'text' => 'text-purple-500'],
                            'out_for_delivery' => ['name' => 'Out for Delivery', 'color' => 'bg-indigo-500', 'text' => 'text-indigo-500'],
                            'delivered'        => ['name' => 'Delivered Orders', 'color' => 'bg-emerald-500', 'text' => 'text-emerald-500'],
                            'cancelled'        => ['name' => 'Cancelled Orders', 'color' => 'bg-red-500', 'text' => 'text-red-500'],
                        ];
                    @endphp

                    @foreach($statuses as $key => $meta)
                        @php
                            $count = $statusBreakdown[$key]['count'] ?? 0;
                            $revenue = $statusBreakdown[$key]['revenue'] ?? 0;
                            $percentage = $totalOrders > 0 ? ($count / $totalOrders) * 100 : 0;
                        @endphp
                        <div class="space-y-1">
                            <div class="flex justify-between text-xs font-semibold">
                                <span class="text-gray-700 dark:text-gray-300">{{ $meta['name'] }}</span>
                                <span class="text-gray-955 dark:text-white">{{ $count }} ({{ number_format($percentage, 0) }}%) - <span class="{{ $meta['text'] }}">₹{{ number_format($revenue, 0) }}</span></span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-850 rounded-full h-2">
                                <div class="{{ $meta['color'] }} h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>

        <!-- Trend and Payment Method Split -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Daily Sales Trend -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm">
                <h4 class="text-base font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-1.5">
                    📈 Daily Sales Trend
                </h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 text-gray-400 font-semibold uppercase">
                                <th class="pb-3 text-left">Date</th>
                                <th class="pb-3 text-center w-20">Orders</th>
                                <th class="pb-3 text-right w-28">Daily Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-850">
                            @forelse($dailyTrend as $trend)
                                <tr>
                                    <td class="py-3 font-semibold text-gray-855 dark:text-gray-150">{{ Carbon\Carbon::parse($trend->date)->format('d M Y') }}</td>
                                    <td class="py-3 text-center font-bold text-gray-600 dark:text-gray-400">{{ $trend->order_count }}</td>
                                    <td class="py-3 text-right font-black text-emerald-600 dark:text-emerald-400">₹{{ number_format($trend->daily_revenue, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-4 text-center text-gray-400 italic">No sales trend data in this range.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payment Method Split -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm">
                <h4 class="text-base font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-1.5">
                    💳 Sales by Payment Method
                </h4>
                <div class="space-y-4">
                    @forelse($paymentBreakdown as $payment)
                        @php
                            $methodName = $payment->payment_method === 'cash_on_delivery' ? '💵 Cash on Delivery (COD)' : '💳 Card / Online Payment';
                            $percentage = $totalRevenue > 0 ? ($payment->revenue / $totalRevenue) * 100 : 0;
                        @endphp
                        <div class="space-y-1">
                            <div class="flex justify-between text-xs font-semibold">
                                <span class="text-gray-700 dark:text-gray-300">{{ $methodName }}</span>
                                <span class="text-gray-955 dark:text-white">{{ $payment->count }} orders (₹{{ number_format($payment->revenue, 2) }})</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-850 rounded-full h-2">
                                <div class="bg-indigo-600 h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-gray-400 italic py-4 text-xs">No payments recorded in this range.</p>
                    @endforelse
                </div>
            </div>

        </div>

        <!-- Date-wise List of Bills / Orders -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm">
            <h4 class="text-base font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-1.5">
                🧾 Date-wise Bill & Invoice Directory
            </h4>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-800 text-gray-400 font-semibold uppercase">
                            <th class="pb-3 text-left">Date / Time</th>
                            <th class="pb-3 text-left">Bill Number</th>
                            <th class="pb-3 text-left">Customer Details</th>
                            <th class="pb-3 text-left">Items Ordered</th>
                            <th class="pb-3 text-right">Amount</th>
                            <th class="pb-3 text-center">Status</th>
                            <th class="pb-3 text-center w-28">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-850">
                        @forelse($bills as $bill)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-850/50 transition duration-150">
                                <td class="py-4 text-gray-500 whitespace-nowrap">{{ $bill->created_at->format('d M Y, h:i A') }}</td>
                                <td class="py-4 font-bold text-gray-900 dark:text-white whitespace-nowrap">{!! $bill->highlighted_order_number_html !!}</td>
                                <td class="py-4">
                                    <div class="font-semibold text-gray-800 dark:text-gray-200">{{ $bill->customer_name }}</div>
                                    <div class="text-[10px] text-gray-400">{{ $bill->customer_phone }}</div>
                                </td>
                                <td class="py-4 max-w-[200px] truncate" title="{{ $bill->items->map(fn($i) => $i->quantity . 'x ' . $i->name)->implode(', ') }}">
                                    {{ $bill->items->map(fn($i) => $i->quantity . 'x ' . $i->name)->implode(', ') }}
                                </td>
                                <td class="py-4 text-right font-bold text-gray-900 dark:text-white whitespace-nowrap">₹{{ number_format($bill->total, 2) }}</td>
                                <td class="py-4 text-center whitespace-nowrap">
                                    @php
                                        $badgeColors = [
                                            'pending'          => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
                                            'accepted'         => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
                                            'preparing'        => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
                                            'out_for_delivery' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400',
                                            'delivered'        => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
                                            'cancelled'        => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                                        ];
                                        $color = $badgeColors[$bill->status] ?? 'bg-gray-100 text-gray-800';
                                    @endphp
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $color }}">
                                        {{ $bill->status }}
                                    </span>
                                </td>
                                <td class="py-4 text-center whitespace-nowrap">
                                    <div style="display: flex; flex-direction: column; gap: 6px; align-items: center; justify-content: center;">
                                        <!-- Print Button -->
                                        <button 
                                            type="button" 
                                            onclick="printBillFromReport('thermal-print-{{ $bill->id }}', '{{ $bill->order_number }}')" 
                                            style="background-color: #22c55e; color: #ffffff; padding: 6px 12px; border-radius: 6px; font-size: 10px; font-weight: bold; display: inline-flex; align-items: center; justify-content: center; gap: 4px; border: none; cursor: pointer; transition: background 0.15s ease-in-out; width: 92px; text-align: center;"
                                            onmouseover="this.style.backgroundColor='#16a34a'"
                                            onmouseout="this.style.backgroundColor='#22c55e'"
                                        >
                                            🖨️ Print
                                        </button>
                                        <!-- View Details Button -->
                                        <a 
                                            href="/admin/orders/{{ $bill->id }}" 
                                            style="background-color: #3b82f6; color: #ffffff; padding: 6px 12px; border-radius: 6px; font-size: 10px; font-weight: bold; display: inline-flex; align-items: center; justify-content: center; gap: 4px; border: none; cursor: pointer; transition: background 0.15s ease-in-out; width: 92px; text-decoration: none; text-align: center;"
                                            onmouseover="this.style.backgroundColor='#2563eb'"
                                            onmouseout="this.style.backgroundColor='#3b82f6'"
                                        >
                                            👁️ Details
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-6 text-center text-gray-400 italic">No bills found in this date range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Hidden Printable Thermal Bills -->
    @foreach($bills as $bill)
        <div style="display: none;">
            <div id="thermal-print-{{ $bill->id }}">
                <div style="font-family: Courier, monospace; width: 74mm; padding: 10px; color: black; background: white; font-size: 10px;">
                    @php
                        $receiptSettings = \App\Models\ReceiptSetting::getSettings();
                    @endphp
                    <div style="text-align: center; margin-bottom: 8px;">
                        <h2 style="margin: 0; font-size: 15px; font-weight: bold; letter-spacing: 1px;">{{ $receiptSettings->outlet_name }}</h2>
                        <p style="margin: 1px 0; font-size: 9px; white-space: pre-line;">{{ $receiptSettings->outlet_address }}</p>
                        <p style="margin: 1px 0; font-size: 9px;">Contact: {{ $receiptSettings->outlet_phone }}</p>
                        @if($receiptSettings->gst_number)
                            <p style="margin: 1px 0; font-size: 9px; font-weight: bold;">GSTIN: {{ $receiptSettings->gst_number }}</p>
                        @endif
                    </div>
                    <div style="border-top: 1px dashed black; margin: 6px 0;"></div>
                    <div style="line-height: 1.3;">
                        <div>BILL ID: <b>{!! $bill->highlighted_order_number_html !!}</b></div>
                        <div>DATE: {{ $bill->created_at->format('d/m/Y h:i A') }}</div>
                        <div>CUST: {{ strtoupper($bill->customer_name) }}</div>
                        <div>PHONE: {{ $bill->customer_phone }}</div>
                        <div>PMT: {{ strtoupper(str_replace('_', ' ', $bill->payment_method)) }}</div>
                        <div>STATUS: {{ strtoupper($bill->status) }}</div>
                    </div>
                    <div style="border-top: 1px dashed black; margin: 6px 0;"></div>
                    <div>
                        <span style="font-weight: bold;">DELIVER TO:</span>
                        <div style="line-height: 1.2; margin-top: 2px;">{{ $bill->delivery_address }}, {{ $bill->city }}</div>
                    </div>
                    <div style="border-top: 1px solid black; margin: 6px 0;"></div>
                    <table style="width: 100%; font-size: 10px; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 1px solid black;">
                                <th style="text-align: left; padding-bottom: 3px;">ITEM DESCRIPTION</th>
                                <th style="text-align: center; padding-bottom: 3px; width: 30px;">QTY</th>
                                <th style="text-align: right; padding-bottom: 3px; width: 60px;">AMOUNT</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bill->items as $item)
                                <tr>
                                    <td style="padding: 3px 0; font-weight: bold;">{{ $item->name }}</td>
                                    <td style="text-align: center; padding: 3px 0;">{{ $item->quantity }}</td>
                                    <td style="text-align: right; padding: 3px 0;">₹{{ number_format($item->subtotal, 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div style="border-top: 1px dashed black; margin: 6px 0;"></div>
                    <div style="line-height: 1.3;">
                        <div style="display: flex; justify-content: space-between;">
                            <span>SUB TOTAL:</span>
                            <span>₹{{ number_format($bill->subtotal, 0) }}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>DELIVERY FEE:</span>
                            <span>₹{{ number_format($bill->delivery_fee, 0) }}</span>
                        </div>
                        <div style="border-top: 1px solid black; margin: 4px 0;"></div>
                        <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 11px;">
                            <span>TOTAL AMOUNT:</span>
                            <span>₹{{ number_format($bill->total, 0) }}</span>
                        </div>
                    </div>
                    @if($bill->special_instructions)
                        <div style="border-top: 1px dashed black; margin: 6px 0;"></div>
                        <div style="font-size: 9px; font-style: italic;">
                            <b>INSTRUCTIONS:</b> {{ $bill->special_instructions }}
                        </div>
                    @endif
                    <div style="border-top: 1px dashed black; margin: 6px 0;"></div>
                    <div style="text-align: center; font-size: 9px; margin-top: 8px;">
                        <p style="margin: 0; font-weight: bold;">THANK YOU FOR YOUR PATRONAGE!</p>
                        <p style="margin: 1px 0;">Swad E Punjab</p>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <!-- Print Helper Script -->
    <script>
        function printBillFromReport(divId, orderNum) {
            const printContents = document.getElementById(divId).innerHTML;
            const printWindow = window.open('', '_blank', 'height=600,width=400');
            
            printWindow.document.write('<html><head><title>Thermal Receipt - ' + orderNum + '<\/title>');
            printWindow.document.write('<style>');
            printWindow.document.write('@page { size: 80mm auto; margin: 0; }');
            printWindow.document.write('@media print { body { margin: 0; padding: 10px; width: 80mm; } }');
            printWindow.document.write('body { font-family: Courier, monospace; background: white; color: black; }');
            printWindow.document.write('<\/style>');
            printWindow.document.write('<\/head><body>');
            printWindow.document.write('<div style="width: 74mm; max-width: 74mm; word-wrap: break-word;">');
            printWindow.document.write(printContents);
            printWindow.document.write('<\/div>');
            printWindow.document.write('<\/body><\/html>');
            printWindow.document.close();
            
            setTimeout(function() {
                printWindow.focus();
                printWindow.print();
                printWindow.close();
            }, 500);
        }
    </script>
</x-filament-panels::page>
