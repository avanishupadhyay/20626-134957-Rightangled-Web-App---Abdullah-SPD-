@extends('admin.layouts.app')
@section('content')
    <style>
        .table-responsive {
            overflow-x: auto;
            width: 100%;
        }
    </style>
    <div class="container">
        <div class="row page-titles mx-0 mb-3">
            <div class="col-sm-6 p-0">
                <div class="welcome-text">
                    <h4>Orders</h4>
                </div>
            </div>
            <div class="col-sm-6 p-0 justify-content-sm-end mt-2 mt-sm-0 d-flex">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Accuracy</li>
                </ol>
            </div>
        </div>

        <div class="row mb-5">
            <!-- Column starts -->
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Search</h4>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ route('accuracychecker_orders.index') }}"
                            class="row g-2 align-items-end">

                            <div class="col-md-3">
                                <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                                    placeholder="Search by Order Number" id="manual-order-input">
                            </div>


                            <div class="col-md-1 d-grid">
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </div>


                            <div class="col-md-1 d-grid">
                                <a href="{{ route('accuracychecker_orders.index') }}" class="btn btn-secondary">Clear</a>
                            </div>
                            {{-- <div class="col-md-3">
                                <input type="text" id="manual-order-input" class="form-control"
                                    placeholder="Manual Order Number">
                            </div> --}}

                            <div class="col-md-3 d-grid">
                                <button type="button" id="manualOpenBtn" class="btn btn-info">Open Order Details</button>
                            </div>

                        </form>

                    </div>

                </div>
            </div>

        </div>

        <div class="row">
            <div class="col-12">

                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>Dispensed Orders</strong>
                    </div>
                    <div class="card-body table-responsive">
                        @if ($orders->isEmpty())
                            <p class="text-muted">No dispensed orders available for accuracy checks.</p>
                        @else
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Order Number</th>
                                        <th>Batch Number</th>
                                        <th>Dispensed By</th>
                                        <th>Dispensed At</th>
                                        <th>Created At</th>
                                        <th>Action</th>

                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($orders as $order)
                                        <tr>

                                            <td>{{ $order->order_number }}</td>
                                            <td>{{ $order->batch_number ?? '-' }}</td>
                                            <td>{{ $order->dispensed_by ?? '-' }}</td>
                                            <td>{{ $order->dispense->created_at->format(config('Reading.date_time_format')) ?? '-' }}
                                            </td>
                                            <td>{{ $order->created_at->format(config('Reading.date_time_format')) }}</td>
                                            </td>
                                            {{-- <td> --}}


                                            <td>
                                                {!! DNS2D::getBarcodeHTML((string) $order->order_number, 'QRCODE', 6, 6) !!}
                                            </td>

                                            <input type="text" id="qr-scan-input" autofocus
                                                style="opacity: 0; position: absolute; left: -9999px;">

                                            {{-- </td> --}}
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>

            </div>
        </div>

        <div class="mt-1">
            {!! $orders->appends(request()->query())->links('pagination::bootstrap-5') !!}
        </div>

        <!-- Order Details Modal -->
        <div class="modal fade" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true"
            data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Order Details - <span id="modalOrderNumber"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p><strong>Customer:</strong> <span id="modalCustomer"></span></p>
                        <p><strong>Shipping Address:</strong><br>
                            <span id="modalShippingAddress"></span>
                        </p>

                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Price</th>

                                </tr>
                            </thead>
                            <tbody id="modalItemsTable">
                                <!-- Items will be injected here -->
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button id="fulfillBtn" class="btn btn-success">Mark as Fulfilled</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

{{-- <script>
    document.addEventListener('DOMContentLoaded', function() {
        let selectedOrderId = null;
        const scanInput = document.getElementById('qr-scan-input');
        let scanTimeout = null;

        // Refocus hidden input
        setInterval(() => scanInput.focus(), 500);

        // Handle scan input
        scanInput.addEventListener('input', function() {
            clearTimeout(scanTimeout);
            scanTimeout = setTimeout(() => {
                const scannedOrderNumber = scanInput.value.trim();
                console.log("Scanned value:", scannedOrderNumber);
                // scanInput.value = '';

                if (scannedOrderNumber === '') return;

                // ✅ Correct fetch URL
                fetch(`/admin/Accuracy-checker/orders/ajax/${scannedOrderNumber}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.status == 'success') {
                            document.getElementById('modalOrderNumber').textContent = data
                                .order.order_number;
                            document.getElementById('modalCustomer').textContent = data
                                .order.email;

                            const tbody = document.getElementById('modalItemsTable');
                            tbody.innerHTML = '';

                            data.items.forEach(item => {
                                const row = `
                                    <tr>
                                        <td>${item.name}</td>
                                        <td>${item.quantity}</td>
                                        <td>${item.price}</td>
                                    </tr>
                                `;
                                tbody.insertAdjacentHTML('beforeend', row);
                            });

                            new bootstrap.Modal(document.getElementById('orderModal'))
                                .show();
                            selectedOrderId = scannedOrderNumber;
                        } else {
                            alert(data.message || 'Order not found.');
                        }
                        scanInput.value = '';
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Error loading order.');
                    });
            }, 300);
        });

        // Fulfill button logic
        document.getElementById('fulfillBtn').addEventListener('click', function() {
            if (!selectedOrderId) return;
            if (!confirm('Mark this order as fulfilled and send dispense mail?')) return;

            fetch(`/admin/Accuracy-checker/orders/fulfill/${selectedOrderId}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert('Order fulfilled and email sent!');
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to fulfill order.');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Fulfillment failed.');
                });
        });
    });
</script> --}}
<!-- Toastr CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

<!-- Toastr JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let selectedOrderId = null;
        const scanInput = document.getElementById('qr-scan-input');
        const manualInput = document.getElementById('manual-order-input');
        const manualBtn = document.getElementById('manualOpenBtn');
        let scanTimeout = null;

        // 🔁 Refocus hidden input only if manual input is not focused
        setInterval(() => {
            if (document.activeElement !== manualInput) {
                scanInput.focus();
            }
        }, 500);

        // 🔄 Reusable fetch + modal handler
        function fetchAndShowOrder(orderNumber) {
            fetch(`/admin/Accuracy-checker/orders/ajax/${orderNumber}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status == 'success') {
                        document.getElementById('modalOrderNumber').textContent = data.order.order_number;
                        document.getElementById('modalCustomer').textContent = data.order.email;
                        const shipping = data.shipping_address;
                        document.getElementById('modalShippingAddress').innerHTML = `
                    ${shipping.name}<br>
                    ${shipping.address1} ${shipping.address2}<br>
                    ${shipping.city} ${shipping.zip}<br>
                    ${shipping.country}<br>
                    Phone: ${shipping.phone}
                `;
                        const tbody = document.getElementById('modalItemsTable');
                        tbody.innerHTML = '';

                        data.items.forEach(item => {
                            const row = `
                                <tr>
                                    <td>${item.name}</td>
                                    <td>${item.quantity}</td>
                                    <td>${item.price}</td>
                                </tr>`;
                            tbody.insertAdjacentHTML('beforeend', row);
                        });

                        new bootstrap.Modal(document.getElementById('orderModal')).show();
                        selectedOrderId = orderNumber;
                    } else {
                        alert(data.message || 'Order not found.');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error loading order.');
                });
        }

        // ✅ Scanner input (auto-detect from USB device)
        scanInput.addEventListener('input', function() {
            clearTimeout(scanTimeout);
            scanTimeout = setTimeout(() => {
                const scannedOrderNumber = scanInput.value.trim();
                if (scannedOrderNumber !== '') {
                    fetchAndShowOrder(scannedOrderNumber);
                }
                scanInput.value = '';
            }, 300);
        });

        // ✅ Manual entry fallback
        manualBtn.addEventListener('click', function() {
            const manualOrder = manualInput.value.trim();
            if (manualOrder === '') {
                alert('Please enter an order number.');
                return;
            }
            fetchAndShowOrder(manualOrder);
        });

        // ✅ Fulfill button logic
        document.getElementById('fulfillBtn').addEventListener('click', function() {
            if (!selectedOrderId) return;
            if (!confirm('Mark this order as fulfilled and send dispense mail?')) return;

            fetch(`/admin/Accuracy-checker/orders/fulfill/${selectedOrderId}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status == 'success') {
                        alert('Order fulfilled and email sent!');
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to fulfill order.');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Fulfillment failed.');
                });
        });
    });
</script>
