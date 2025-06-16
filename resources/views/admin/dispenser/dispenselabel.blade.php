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

        .product-table th, .product-table td {
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

@foreach ($processedOrders as $order)
    <div class="order-block">
        {{-- Order Header --}}
        <h4>Dispensing Label</h4>
        <p><strong>Order Number:</strong> {{ $order->order_number }}</p>
        <p><strong>Prescription Date:</strong> {{ $order->order_data['created_at'] ?? 'N/A' }}</p>
        <p><strong>Customer:</strong> {{ $order->order_data['customer']['first_name'] ?? '' }} {{ $order->order_data['customer']['last_name'] ?? '' }}</p>
        <p><strong>DOB:</strong> {{ $order->order_data['customer']['dob'] ?? 'N/A' }}</p>
        <p><strong>Prescriber:</strong> {{ $order->order_data['prescriber_name'] ?? 'N/A' }}</p>

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
                        <tr>
                            <td>{{ $item['title'] ?? 'N/A' }}</td>
                            <td>{{ $item['direction_of_use'] ?? 'Not available' }}</td>
                            <td>x {{ $item['quantity'] ?? 0 }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Shipping Section --}}
        <div class="section">
            <h4>Shipping / Packing Slip</h4>
            @php
                $shipping = strtolower($order->order_data['shipping_lines'][0]['title'] ?? '');
            @endphp

            @if (str_contains($shipping, 'local'))
                <p><strong>Local Pickup / Delivery</strong></p>
                <p><strong>Address:</strong>
                    {{ $order->order_data['shipping_address']['address1'] ?? '' }},
                    {{ $order->order_data['shipping_address']['city'] ?? '' }},
                    {{ $order->order_data['shipping_address']['zip'] ?? '' }}
                </p>
            @else
                <p>Shipping Label Placeholder (Royal Mail / DHL)</p>
            @endif
        </div>
    </div>

    {{-- Page break between orders --}}
    @if (!$loop->last)
        <div class="page-break"></div>
    @endif
@endforeach

</body>
</html>
