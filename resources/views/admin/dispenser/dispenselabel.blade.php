<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Dispense Batch PDF</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #000;
            line-height: 1.5;
        }

        h4 {
            margin-bottom: 6px;
            font-size: 14px;
            text-transform: uppercase;
        }

        .section {
            margin-top: 10px;
        }

        .product-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .product-table th,
        .product-table td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
        }

        .page-break {
            page-break-after: always;
        }

        p {
            margin: 2px 0;
        }
    </style>
</head>

<body>

    {{-- 1. Dispensing Labels Section --}}
    @foreach ($processedOrders as $order)
        @php
            $prescriber_data = getPrescriberData($order->order_number);
        @endphp
        <div class="order-block">
            <div style="text-align: center; margin-top: 10px;">
                <h4>Dispensing Label</h4>
                <p><strong>Order Number:</strong> {{ $order->order_number }}</p>
                <p><strong>Prescription Date:</strong>
                    {{ isset($prescriber_data->updated_at) ? $prescriber_data->updated_at : '' }}</p>
                <p><strong>Customer:</strong> {{ $order->order_data['customer']['first_name'] ?? '' }}
                    {{ $order->order_data['customer']['last_name'] ?? '' }}</p>
                <p><strong>DOB:</strong> {{ $order->order_data['customer']['dob'] ?? 'N/A' }}</p>
                <p><strong>Prescriber:</strong>
                    {{ isset($prescriber_data->id) ? ucfirst(getUserName($prescriber_data->id)) ?? '-' : '-' }}</p>
            </div>

            {{-- Line Items --}}
            <div class="section">
                <table class="product-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Direction of Use</th>
                            <th>Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->line_items as $item)
                            @if (!empty($item['current_quantity']) && $item['current_quantity'] > 0)
                                <tr>
                                    <td>{{ $item['title'] ?? 'N/A' }}</td>
                                    <td>{{ $item['direction_of_use'] ?? 'Not available' }}</td>
                                    <td>x {{ $item['current_quantity'] }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>

                </table>
            </div>
            <br>

            <div style="text-align: center; margin-top: 10px;">
                <strong>Rightangled Clinic</strong>
                GPHC reg number: 9011933 {{ config('Site.location') }}<br>
                <p style="margin: 5px 0;">Keep out of the reach and sight of children. Store in dry place and away from
                    light</p>
                <strong>Dispensed by:</strong> {{ auth()->user()->name }}<br>
                <strong>Packed by:</strong> {{ auth()->user()->name }}
            </div>


        </div>

        {{-- Page break between labels --}}
        @if (!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach

    {{-- 2. Packaging Slips Section --}}
    @php
        $groupedOrders = $processedOrders->groupBy('slip_type');
    @endphp

    {{-- Shared Packaging Slip for HQ --}}
    @if ($groupedOrders->has('hq'))
        <div class="order-block">
            <h4>Packaging Slip</h4>
            <p><strong>RIGHTANGLED Orders</strong></p>
            <p>{{ now()->format('d F Y') }}</p>

            {{-- BILL TO --}}
            @foreach ($groupedOrders['hq'] as $order)
                {{-- BILL TO --}}
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <!-- SHIP TO -->
                        <td style="width: 48%; vertical-align: top;">
                            <p style="margin: 0; font-weight: bold;">SHIP TO</p>
                            <p style="margin: 0;">No shipping address</p>
                        </td>

                        <!-- BILL TO -->
                        <td style="width: 48%; vertical-align: top;">
                            <p style="margin: 0; font-weight: bold;">BILL TO</p>
                            <p style="margin: 0;">{{ $order->bill_to['name'] ?? '' }}</p>
                            <p style="margin: 0;">{{ $order->bill_to['address1'] ?? '' }}</p>
                            <p style="margin: 0;">
                                {{ $order->bill_to['city'] ?? '' }} {{ $order->bill_to['province'] ?? '' }}
                                {{ $order->bill_to['zip'] ?? '' }}
                            </p>
                            <p style="margin: 0;">{{ $order->bill_to['country'] ?? '' }}</p>
                        </td>
                    </tr>
                </table>


                {{-- ITEMS --}}
                <table class="product-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->line_items as $item)
                            @if (!empty($item['current_quantity']) && $item['current_quantity'] > 0)
                                <tr>
                                    <td>{{ $item['title'] ?? 'N/A' }}</td>
                                    <td>{{ $item['current_quantity'] ?? 0 }} Tablets</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @endforeach


            <p><strong>NOTES</strong></p>
            <p>Customer already completed ID verification</p>

            <p>Thank you for shopping with us!</p>
            <p><strong>Rightangled</strong><br>
                32 Road, London W6 0LT, United Kingdom<br>
                info@rightangled.com<br>
                rightangled.com</p>
        </div>
        @if (!$loop->last)
            <div class="page-break"></div>
        @endif
    @endif

    {{-- Separate Packaging Slips for Local Delivery --}}
    @if ($groupedOrders->has('local'))
        @foreach ($groupedOrders['local'] as $order)
            <div class="order-block">
                <h4>Packaging Slip</h4>
                <p><strong>RIGHTANGLED Order #{{ $order->name ?? $order->order_number }}</strong></p>
                <p>{{ \Carbon\Carbon::parse($order->created_at)->format('d F Y') }}</p>

                {{-- SHIP TO --}}
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <!-- SHIP TO -->
                        <td style="width: 50%; vertical-align: top;">
                            <p style="margin: 0; font-weight: bold;">SHIP TO</p>
                            <p style="margin: 0;">{{ $order->ship_to['name'] ?? '' }}</p>
                            <p style="margin: 0;">{{ $order->ship_to['address1'] ?? '' }}</p>
                            <p style="margin: 0;">
                                {{ $order->ship_to['city'] ?? '' }} {{ $order->ship_to['province'] ?? '' }}
                                {{ $order->ship_to['zip'] ?? '' }}
                            </p>
                            <p style="margin: 0;">{{ $order->ship_to['country'] ?? '' }}</p>
                        </td>

                        <!-- BILL TO -->
                        <td style="width: 50%; vertical-align: top;">
                            <p style="margin: 0; font-weight: bold;">BILL TO</p>
                            <p style="margin: 0;">{{ $order->bill_to['name'] ?? '' }}</p>
                            <p style="margin: 0;">{{ $order->bill_to['address1'] ?? '' }}</p>
                            <p style="margin: 0;">
                                {{ $order->bill_to['city'] ?? '' }} {{ $order->bill_to['province'] ?? '' }}
                                {{ $order->bill_to['zip'] ?? '' }}
                            </p>
                            <p style="margin: 0;">{{ $order->bill_to['country'] ?? '' }}</p>
                        </td>
                    </tr>
                </table>



                {{-- Items --}}
                <table class="product-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->line_items as $item)
                            @if (!empty($item['current_quantity']) && $item['current_quantity'] > 0)
                                <tr>
                                    <td>{{ $item['title'] ?? 'N/A' }}</td>
                                    <td>{{ $item['current_quantity'] ?? 0 }} </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
                {{-- Notes and Footer --}}
                <p><strong>NOTES</strong></p>
                <p>Customer already completed ID verification</p>

                <p>Thank you for shopping with us!</p>
                <p><strong>Rightangled</strong><br>
                    32 Road, London W6 0LT, United Kingdom<br>
                    info@rightangled.com<br>
                    rightangled.com</p>
            </div>
            @if (!$loop->last)
                <div class="page-break"></div>
            @endif
        @endforeach
    @endif
</body>

</html>
