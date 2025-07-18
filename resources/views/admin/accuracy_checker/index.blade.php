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
                            @role('ACT')
                                <div class="col-md-3 d-grid">
                                    <button type="button" id="manualOpenBtn" class="btn btn-info">Open Order Details</button>
                                </div>
                            @endrole

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
                                        <th>Store Name</th>
                                        @role('ACT')
                                            <th>Action</th>
                                        @endrole

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
                                            <td>{{ ucfirst($order->store->name ?? 'NA') }}</td>
                                            </td>
                                            {{-- <td> --}}

                                            @role('ACT')
                                                {{-- <td>
                                                    {!! DNS2D::getBarcodeHTML((string) $order->order_number, 'QRCODE', 6, 6) !!}
                                                    <p>Scan order QR</p>
                                                </td> --}}
                                            @endrole

                                            {{-- <input type="text" id="qr-scan-input" autofocus
                                                style="opacity: 0; position: absolute; left: -9999px;"> --}}

                                            {{-- </td> --}}
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <input type="text" id="qr-scan-input" autofocus
                                style="opacity: 0; position: absolute; left: -9999px;">
                        @endif
                    </div>
                </div>

            </div>
        </div>

        <div class="mt-1">
            {!! $orders->appends(request()->query())->links('pagination::bootstrap-5') !!}
        </div>

        <!-- Order Details Modal -->
        <div class="modal fade mt-2" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true"
            data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-xl">
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
                                    <th style="width:50px">Product SKU</th> <!-- new column -->


                                </tr>
                            </thead>
                            <tbody id="modalItemsTable">
                                <!-- Items will be injected here -->
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer d-flex justify-content-between align-items-center">
                        <div id="barcodeContainer" class="me-auto" style="display: none;">
                            <p style="margin-bottom: 8px; font-weight: bold; color: #333;">
                                Scan this barcode to fulfill the order
                            </p>
                            <img id="barcodeImage" src="" alt="Barcode">

                        </div>
                    </div>
                    <input type="text" id="product-barcode-scan" autocomplete="off" placeholder="product-barcode"
                        style="opacity: 0; position: absolute; left: -9999px;">
                    {{-- style="opacity: 0; position: absolute; left: -9999px;" --}}

                    <input type="text" id="barcode-scan-input" disabled style="display:none;" autocomplete="off">
                    {{-- <input type="text" id="product-barcode-scan" autocomplete="off" placeholder="product-barcode"> --}}



                </div>
            </div>


        </div>



    </div>
    <div id="loaderOverlay"
        style="display: none; position: fixed; top: 0; left: 0; 
     width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; 
     justify-content: center; align-items: center;">
        <div class="spinner-border text-light" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

@endsection



{{-- <script>
    document.addEventListener('DOMContentLoaded', function() {
        let selectedOrderId = null;
        const requiredProductIds = new Set();
        const scannedProductIds = new Set();

        const scanInput = document.getElementById('qr-scan-input');
        const manualInput = document.getElementById('manual-order-input');
        const manualBtn = document.getElementById('manualOpenBtn');

        const productBarcodeInput = document.getElementById('product-barcode-scan');
        const orderBarcodeInput = document.getElementById('barcode-scan-input');
        const orderModal = document.getElementById('orderModal');
        const barcodeImageContainer = document.getElementById('barcodeContainer');
        const barcodeImage = document.getElementById('barcodeImage');

        let scanTimeout = null;

        // Refocus hidden input every 500ms (only if modal not open)
        setInterval(() => {
            if (document.activeElement !== manualInput && !orderModal.classList.contains('show')) {
                scanInput.focus();
            }
        }, 500);
     

        // --- Fetch and show modal ---
        function fetchAndShowOrder(orderNumber) {
            fetch(`/admin/Accuracy-checker/orders/ajax/${orderNumber}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        selectedOrderId = data.order.order_number;
                        document.getElementById('modalOrderNumber').textContent = selectedOrderId;
                        document.getElementById('modalCustomer').textContent = data.order.email;

                        const shipping = data.shipping_address;
                        document.getElementById('modalShippingAddress').innerHTML = `
                        ${shipping.name}<br>${shipping.address1} ${shipping.address2}<br>
                        ${shipping.city} ${shipping.zip}<br>${shipping.country}<br>
                        Phone: ${shipping.phone}
                    `;

                        const tbody = document.getElementById('modalItemsTable');
                        tbody.innerHTML = '';
                        requiredProductIds.clear();
                        scannedProductIds.clear();

                        orderBarcodeInput.disabled = true;
                        orderBarcodeInput.style.display = 'none';
                        productBarcodeInput.value = '';
                        barcodeImageContainer.style.display = 'none';

                        data.items.forEach(item => {
                            requiredProductIds.add(item.product_id.toString());
                            const row = `
                            <tr>
                                <td>${item.name}</td>
                                <td>${item.quantity}</td>
                                <td>${item.price}</td>
                                <td>
                                    <img src="${item.barcode_base64}" 
                                         alt="Product Barcode"
                                         class="product-barcode"
                                         style="height: 60px;"
                                         data-product-id="${item.product_id}"
                                         data-order-id="${data.order.order_number}">
                                </td>
                            </tr>`;
                            tbody.insertAdjacentHTML('beforeend', row);
                        });
                        console.log("required", requiredProductIds)

                        barcodeImage.src = '/barcode/' + selectedOrderId;

                        new bootstrap.Modal(orderModal).show();
                        setTimeout(() => productBarcodeInput.focus(), 300);
                    } else {
                        alert(data.message || 'Order not found.');
                    }
                })
                .catch(() => alert('Error fetching order details.'));
        }

        // --- Dashboard scan input ---
        scanInput.addEventListener('input', function() {
            clearTimeout(scanTimeout);
            scanTimeout = setTimeout(() => {
                const scannedOrderNumber = scanInput.value.trim();
                scanInput.value = '';
                if (scannedOrderNumber) {
                    fetchAndShowOrder(scannedOrderNumber);
                }
            }, 300);
        });

        // --- Manual entry ---
        manualBtn?.addEventListener('click', () => {
            const manualOrder = manualInput.value.trim();
            if (!manualOrder) {
                alert('Please enter an order number.');
                return;
            }
            fetchAndShowOrder(manualOrder);
        });

        // --- Product Scanner ---
        let productBuffer = '';
        let productTimer;
        document.addEventListener('keydown', function(e) {
            if (!orderModal.classList.contains('show')) return;
            if (orderBarcodeInput && document.activeElement === orderBarcodeInput)
                return; // Ignore when order QR is focused

            // Optional: you can skip if product input is not visible
            if (productBarcodeInput.offsetParent === null) return;

            if (e.key.length === 1) {
                productBuffer += e.key;
            }

            clearTimeout(productTimer);
            productTimer = setTimeout(() => {
                const scanned = productBuffer.trim();
                productBuffer = '';
                productBarcodeInput.value = '';

                if (!scanned) return;

                if (scanned.length < 2) {
                    productBuffer = '';
                    productBarcodeInput.value = '';
                    return; // Too short to be a valid scan
                }


                const matched = document.querySelector(`img[data-product-id="${scanned}"]`);
                if (matched) {
                    const productId = matched.dataset.productId;
                    const orderId = matched.dataset.orderId;

                    // Fetch stock
                    fetch(`/admin/Accuracy-checker/product-stock/${productId}/${orderId}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                toastr.success(
                                    `✅ ${data.product_title}<br>Stock: ${data.stock}`);
                            } else {
                                toastr.error(data.message || '❌ Could not fetch stock');
                            }
                        })
                        .catch(() => toastr.error('⚠️ Shopify error'));

                    scannedProductIds.add(productId);

                    if (scannedProductIds.size === requiredProductIds.size) {
                        toastr.success(
                            '🎉 All products scanned. Scan shipment label QR to fulfil');
                        orderBarcodeInput.disabled = false;
                        orderBarcodeInput.style.display = 'block';
                        orderBarcodeInput.style.opacity = '0'; // hides it
                        orderBarcodeInput.style.position = 'absolute';
                        orderBarcodeInput.style.left = '-9999px';
                        barcodeImageContainer.style.display = 'block';
                        setTimeout(() => orderBarcodeInput.focus(), 100);
                    }
                } else {
                    toastr.error(`❌ product_id ${scanned} not found.`);
                }
            }, 200);
        });


        let orderBuffer = '';
        let orderTimer;

        const loader = document.getElementById('loaderOverlay'); // ✅ Get once at the top

        document.addEventListener('keydown', function(e) {
            if (!orderModal.classList.contains('show') || orderBarcodeInput.disabled) return;
            if (document.activeElement !== orderBarcodeInput) return;

            if (e.key.length === 1) orderBuffer += e.key;

            clearTimeout(orderTimer);
            orderTimer = setTimeout(() => {
                const scanned = orderBuffer.trim();
                orderBuffer = '';
                orderBarcodeInput.value = '';

                if (scanned.length < 2) {
                    orderBuffer = '';
                    orderBarcodeInput.value = '';
                    return; // Too short to be a valid scan
                }

                console.log("scanned", scanned);
                console.log("selected", selectedOrderId);

                if (scanned !== selectedOrderId.toString().trim()) {
                    toastr.error('❌ Order QR does not match.');
                    return;
                }

                // ✅ Show confirm and then loader
                if (confirm(`Mark order ${scanned} as fulfilled?`)) {
                    if (loader) loader.style.display = 'flex'; // ✅ Safe check

                    fetch(`/admin/Accuracy-checker/orders/fulfill/${selectedOrderId}`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                            }
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (loader) loader.style.display = 'none'; // ✅ Safe check

                            if (data.status === 'success') {
                                toastr.success(
                                    '✅ Order fulfilled email notification sent to user');
                                location.reload();
                            } else {
                                toastr.error(data.message || '❌ Fulfillment failed');
                            }
                        })
                        .catch(() => {
                            if (loader) loader.style.display = 'none';
                            toastr.error('❌ Fulfillment error');
                        });
                }
            }, 200);
        });


    });
</script> --}}

{{-- code 30-06-2025 --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        let selectedOrderId = null;
        let trackingNumber=null;
        const requiredProductIds = new Set();
        const scannedProductIds = new Set();
        let scanningMode = 'product';

        const scanInput = document.getElementById('qr-scan-input');
        const manualInput = document.getElementById('manual-order-input');
        const manualBtn = document.getElementById('manualOpenBtn');

        const productBarcodeInput = document.getElementById('product-barcode-scan');
        const orderBarcodeInput = document.getElementById('barcode-scan-input');
        const orderModal = document.getElementById('orderModal');
        const barcodeImageContainer = document.getElementById('barcodeContainer');
        const barcodeImage = document.getElementById('barcodeImage');
        const loader = document.getElementById('loaderOverlay');

        let scanTimeout = null;

        // Focus logic
        setInterval(() => {
            const activeEl = document.activeElement;
            if (!orderModal.classList.contains('show')) {
                if (activeEl !== scanInput && activeEl.tagName !== 'INPUT') {
                    scanInput.focus();
                }
            } else {
                if (scanningMode === 'fulfill' && activeEl !== orderBarcodeInput) {
                    orderBarcodeInput.focus();
                } else if (scanningMode === 'product' && activeEl !== productBarcodeInput) {
                    productBarcodeInput.focus();
                }
            }
        }, 300);

        // Fetch & open order modal
        function fetchAndShowOrder(orderNumber) {
            fetch(`/admin/Accuracy-checker/orders/ajax/${orderNumber}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        selectedOrderId = data.order.order_number;
                        trackingNumber=data.order.tracking_number;
                        document.getElementById('modalOrderNumber').textContent = selectedOrderId;
                        document.getElementById('modalCustomer').textContent = data.order.email;

                        const shipping = data.shipping_address;
                        document.getElementById('modalShippingAddress').innerHTML = `
                        ${shipping.name}<br>${shipping.address1} ${shipping.address2}<br>
                        ${shipping.city} ${shipping.zip}<br>${shipping.country}<br>
                        Phone: ${shipping.phone}
                    `;

                        const tbody = document.getElementById('modalItemsTable');
                        tbody.innerHTML = '';
                        requiredProductIds.clear();
                        scannedProductIds.clear();
                        scanningMode = 'product';

                        orderBarcodeInput.disabled = true;
                        orderBarcodeInput.style.display = 'none';
                        productBarcodeInput.value = '';
                        barcodeImageContainer.style.display = 'none';

                        data.items.forEach(item => {
                            requiredProductIds.add(item.product_id.toString());
                            const row = `
                            <tr>
                                <td>${item.name}</td>
                                <td>${item.quantity}</td>
                                <td> £ ${item.price}</td>
                              <td id="scan-result-${item.sku}">
        <!-- Will fill this after scan -->
    </td>
  
                            </tr>`;
                            tbody.insertAdjacentHTML('beforeend', row);
                        });

                        barcodeImage.src = '/barcode/' + selectedOrderId;
                        new bootstrap.Modal(orderModal).show();
                        console.log(requiredProductIds);
                        setTimeout(() => productBarcodeInput.focus(), 300);
                    } else {
                        toastr.error(data.message || 'Order not found.');
                    }
                })
                .catch(() => toastr.error('Error fetching order details.'));
        }

        // Dashboard QR scan
        scanInput.addEventListener('input', function() {
            clearTimeout(scanTimeout);
            scanTimeout = setTimeout(() => {
                const scannedOrderNumber = scanInput.value.trim();
                scanInput.value = '';
                if (scannedOrderNumber) {
                    fetchAndShowOrder(scannedOrderNumber);
                }
            }, 300);
        });

        // Manual "Open Order" click
        manualBtn?.addEventListener('click', () => {
            const manualOrder = manualInput.value.trim();
            if (!manualOrder) {
                toastr.error('Please enter an order number.');
                return;
            }
            scanningMode = 'product';
            fetchAndShowOrder(manualOrder);
        });

        let productBuffer = '';
        let productTimer;

        document.addEventListener('keydown', function(e) {
            const activeEl = document.activeElement;

            // ❌ Skip scanner logic if typing in non-scanner input
            if (
                activeEl.tagName === 'INPUT' &&
                activeEl.id !== 'product-barcode-scan' &&
                activeEl.id !== 'barcode-scan-input'
            ) return;

            if (!orderModal.classList.contains('show')) return;
            if (scanningMode !== 'product') return;

            if (e.key.length === 1) productBuffer += e.key;

            clearTimeout(productTimer);
            productTimer = setTimeout(() => {
                const scanned = productBuffer.trim();
                productBuffer = '';
                productBarcodeInput.value = '';

                if (!scanned || scanned.length < 2) return;

                const scanResultCell = document.getElementById(`scan-result-${scanned}`);

                if (scanResultCell && !scanResultCell.classList.contains('scanned')) {
                    const productId = scanned;
                    const orderId = selectedOrderId;

                    fetch(`/admin/Accuracy-checker/product-stock/${productId}/${orderId}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                scanResultCell.innerHTML = `
                            <span class="text-success fw-bold">✅ Verified</span><br>
                            <small class="text-muted">SKU: ${productId}</small>
                        `;
                                scanResultCell.classList.add('scanned');
                            } else {
                                toastr.error(data.message || '❌ Could not fetch stock');
                            }
                        })
                        .catch(() => toastr.error('⚠️ Shopify error'));

                    scannedProductIds.add(productId);

                    if (scannedProductIds.size === requiredProductIds.size) {
                        toastr.success(
                            '🎉 All products scanned. Now scan the Shipment label QR to fulfill'
                        );
                        scanningMode = 'fulfill';
                        orderBarcodeInput.disabled = false;
                        orderBarcodeInput.style.display = 'block';
                        orderBarcodeInput.style.opacity = '0';
                        orderBarcodeInput.style.position = 'absolute';
                        orderBarcodeInput.style.left = '-9999px';
                        // barcodeImageContainer.style.display = 'block';

                        setTimeout(() => orderBarcodeInput.focus(), 100);
                    }
                } else {
                    toastr.error(`❌ product_id ${scanned} not found or already scanned.`);
                }
            }, 200);
        });


        // Fulfill Scanner
        let orderBuffer = '';
        let orderTimer;

        document.addEventListener('keydown', function(e) {
            const activeEl = document.activeElement;

            // ❌ Skip if typing in other inputs
            if (
                activeEl.tagName === 'INPUT' &&
                activeEl.id !== 'product-barcode-scan' &&
                activeEl.id !== 'barcode-scan-input'
            ) return;

            if (!orderModal.classList.contains('show')) return;
            if (scanningMode !== 'fulfill') return;
            if (activeEl !== orderBarcodeInput) return;

            if (e.key.length === 1) orderBuffer += e.key;

            clearTimeout(orderTimer);
            orderTimer = setTimeout(() => {
                let scanned = orderBuffer;
                if (scanned.startsWith('JJD')) {
                    scanned = scanned.substring(1);
                }
                orderBuffer = '';
                orderBarcodeInput.value = '';

                if (scanned.length < 2) return;

                if (scanned !== trackingNumber.toString().trim()) {
                    toastr.error('❌ Order QR does not match.');
                    return;
                }
               

                // if (confirm(`Mark order ${scanned} as fulfilled?`)) {
                loader.style.display = 'flex';

                fetch(`/admin/Accuracy-checker/orders/fulfill/${trackingNumber}`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                        }
                    })
                    .then(res => res.json())
                    .then(data => {
                        loader.style.display = 'none';
                        if (data.status === 'success') {
                            toastr.success('✅ Order fulfilled. Email sent.');
                            location.reload();
                        } else {
                            toastr.error(data.message || '❌ Fulfillment failed');
                        }
                    })
                    .catch(() => {
                        loader.style.display = 'none';
                        toastr.error('❌ Fulfillment error');
                    });
                // }
            }, 200);
        });
    });
</script>
