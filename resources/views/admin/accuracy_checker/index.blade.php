@extends('admin.layouts.app')
@section('content')
    <style>
        .table-responsive {
            overflow-x: auto;
            width: 100%;
        }
    </style>
    <style>
        /* Ensure visibility of toastr messages */
        #toast-container>.toast {
            color: #fff !important;
            /* White text */
            background-color: #333 !important;
            /* Dark background for contrast */
            font-weight: 500;
        }

        #toast-container>.toast-success {
            background-color: #28a745 !important;
        }

        #toast-container>.toast-error {
            background-color: #dc3545 !important;
        }

        #toast-container>.toast-info {
            background-color: #17a2b8 !important;
        }

        #toast-container>.toast-warning {
            background-color: #ffc107 !important;
            color: #000 !important;
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
                                    <th style="width:50px">Product Barcode</th> <!-- new column -->


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
                            {{-- <button id="manualFulfillBtn" class="btn btn-success mt-2">
                                ✅ Fulfill Manually
                            </button> --}}
                        </div>
                    </div>
                    <input type="text" id="product-barcode-scan" autocomplete="off" placeholder="product-barcode"
                        style="opacity: 0; position: absolute; left: -9999px;">

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

<!-- ✅ jQuery must come first -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- ✅ Then toastr -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
    toastr.options = {
        closeButton: false,
        progressBar: true,
        positionClass: 'toast-top-right',
        timeOut: 5000
    };
</script>


{{-- 
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
                        document.getElementById('barcodeImage').src = '/barcode/' + data.order.order_number;


                        data.items.forEach(item => {
                            const row = `
                                <tr>
                                    <td>${item.name}</td>
                                    <td>${item.quantity}</td>
                                    <td>${item.price}</td>
                                      <td>
                <img src="${item.barcode_base64}" alt="Product Barcode" class="product-barcode" style="height: 60px;"     data-product-id="${item.product_id}"
    data-order-id='${data.order.order_number}'>

            </td>
                                </tr>`;
                            tbody.insertAdjacentHTML('beforeend', row);
                        });

                        new bootstrap.Modal(document.getElementById('orderModal')).show();
                        setSelectedOrderIdForFulfillment(data.order
                            .order_number); // 👈 Add this to set selectedOrderId

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

    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const barcodeInput = document.getElementById('barcode-scan-input');
        const orderModal = document.getElementById('orderModal');
        let barcodeBuffer = '';
        let barcodeTimer;
        let selectedOrderId = null;

        // 🔁 This should be set when modal is opened and order is loaded
        window.setSelectedOrderIdForFulfillment = function(orderId) {
            selectedOrderId = orderId;
            console.log(selectedOrderId);
        };

        // ✅ Focus input when modal is opened, and clear input when closed
        const observer = new MutationObserver((mutationsList) => {
            for (let mutation of mutationsList) {
                if (
                    mutation.type === 'attributes' &&
                    mutation.attributeName === 'class'
                ) {
                    const isVisible = orderModal.classList.contains('show');

                    if (isVisible) {
                        setTimeout(() => {
                            barcodeInput.focus();
                            barcodeBuffer = '';
                            console.log('Modal opened, focusing barcode input.');
                        }, 200);
                    } else {
                        // 🔻 Modal closed — clear input and buffer
                        barcodeBuffer = '';
                        barcodeInput.value = '';
                        console.log('Modal closed, cleared barcode input.');
                    }
                }
            }
        });

        observer.observe(orderModal, {
            attributes: true
        });

        // ✅ Accumulate scanned characters and handle scan
        document.addEventListener('keydown', function(e) {
            if (!orderModal.classList.contains('show')) return;

            if (e.key.length === 1) {
                barcodeBuffer += e.key;
            }

            clearTimeout(barcodeTimer);
            barcodeTimer = setTimeout(() => {
                const scannedValue = barcodeBuffer.trim();
                if (scannedValue) {
                    barcodeInput.value = scannedValue;

                    if (!selectedOrderId) {
                        alert('Order ID not set.');
                        return;
                    }

                    if (scannedValue !== selectedOrderId.toString()) {
                        alert('Scanned barcode does not match this order.');
                    } else {
                        if (confirm(`Mark order ${scannedValue} as fulfilled?`)) {
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
                                    alert('Fulfillment request failed.');
                                });
                        }
                    }

                    barcodeBuffer = '';
                }
            }, 200);
        });
    });
</script>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const productBarcodeInput = document.getElementById('product-barcode-scan');
    const orderModal = document.getElementById('orderModal');
    let productBuffer = '';
    let productTimer;

    // Focus barcode input when modal opens
    const productObserver = new MutationObserver(() => {
        if (orderModal.classList.contains('show')) {
            setTimeout(() => {
                productBarcodeInput.focus();
                productBuffer = '';
            }, 200);
        }
    });

    productObserver.observe(orderModal, { attributes: true });

    // Scan barcode and check product
    document.addEventListener('keydown', function (e) {
        if (!orderModal.classList.contains('show')) return;

        if (e.key.length === 1) {
            productBuffer += e.key;
        }

        clearTimeout(productTimer);
        productTimer = setTimeout(() => {
            const scanned = productBuffer.trim();
            if (scanned) {
                const matched = document.querySelector(`img[data-product-id="${scanned}"]`);
                if (matched) {
                    const productId = matched.getAttribute('data-product-id');
                    const orderId = matched.getAttribute('data-order-id');
                    console.log(orderId);

                    toastr.info(`🔎 SKU Matched: ${scanned}<br>Fetching real-time stock...`);

                    fetch(`/admin/Accuracy-checker/product-stock/${productId}/${orderId}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                toastr.success(`✅ ${data.product_title}<br>Stock: ${data.stock}`);
                            } else {
                                toastr.error(data.message || '❌ Could not fetch stock');
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            toastr.error('⚠️ Error contacting Shopify.');
                        });
                } else {
                    toastr.error(`❌ SKU ${scanned} not found in this order.`);
                }
            }

            productBuffer = '';
            productBarcodeInput.value = '';
        }, 200);
    });
});
</script> --}}



{{-- //new code 26-06 --}}
{{-- <script>
    let requiredProductIds = new Set();
    let scannedProductIds = new Set();
    let selectedOrderId = null;

    document.addEventListener('DOMContentLoaded', function() {
        const scanInput = document.getElementById('qr-scan-input');
        const manualInput = document.getElementById('manual-order-input');
        const manualBtn = document.getElementById('manualOpenBtn');

        const productBarcodeInput = document.getElementById('product-barcode-scan');
        const orderBarcodeInput = document.getElementById('barcode-scan-input');
        const orderModal = document.getElementById('orderModal');

        // --- Focus QR scanner on dashboard ---
        setInterval(() => {
            if (document.activeElement !== manualInput && !orderModal.classList.contains('show')) {
                scanInput.focus();
            }
        }, 500);

        // --- Manually open modal ---
        manualBtn.addEventListener('click', function() {
            const manualOrder = manualInput.value.trim();
            if (manualOrder === '') {
                alert('Please enter an order number.');
                return;
            }
            fetchAndShowOrder(manualOrder);
        });

        // --- QR scan input from dashboard ---
        scanInput.addEventListener('input', function() {
            const scannedOrderNumber = scanInput.value.trim();
            if (scannedOrderNumber !== '') {
                fetchAndShowOrder(scannedOrderNumber);
            }
            scanInput.value = '';
        });

        // --- Load order details into modal ---
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
                            ${shipping.name}<br>
                            ${shipping.address1} ${shipping.address2}<br>
                            ${shipping.city} ${shipping.zip}<br>
                            ${shipping.country}<br>
                            Phone: ${shipping.phone}
                        `;

                        const tbody = document.getElementById('modalItemsTable');
                        tbody.innerHTML = '';

                        requiredProductIds.clear();
                        scannedProductIds.clear();

                        orderBarcodeInput.disabled = true;
                        orderBarcodeInput.style.display = 'none';
                        productBarcodeInput.value = '';

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

                        document.getElementById('barcodeImage').src = '/barcode/' + selectedOrderId;
                        new bootstrap.Modal(orderModal).show();

                        setTimeout(() => productBarcodeInput.focus(), 300);
                    } else {
                        alert(data.message || 'Order not found.');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error fetching order details.');
                });
        }

        // --- Product Barcode Scanner ---
        let productBuffer = '';
        let productTimer;

        document.addEventListener('keydown', function(e) {
            if (!orderModal.classList.contains('show')) return;

            if (e.key.length === 1) {
                productBuffer += e.key;
            }

            clearTimeout(productTimer);
            productTimer = setTimeout(() => {
                const scanned = productBuffer.trim();
                productBuffer = '';
                productBarcodeInput.value = '';

                if (!scanned) return;

                const matched = document.querySelector(`img[data-product-id="${scanned}"]`);
                if (matched) {
                    const productId = matched.getAttribute('data-product-id');
                    const orderId = matched.getAttribute('data-order-id');

                    if (!scannedProductIds.has(productId)) {
                        scannedProductIds.add(productId);

                        fetch(`/admin/Accuracy-checker/product-stock/${productId}/${orderId}`)
                            .then(res => res.json())
                            .then(data => {
                                if (data.status === 'success') {
                                    toastr.success(
                                        `✅ ${data.product_title}<br>Stock: ${data.stock}`
                                    );
                                } else {
                                    toastr.error(data.message || '❌ Stock fetch failed');
                                }
                            })
                            .catch(err => {
                                console.error(err);
                                toastr.error('⚠️ Shopify fetch error');
                            });
                    } else {
                        toastr.warning('⚠️ Product already scanned.');
                    }

                    // ✅ All products scanned
                    if (scannedProductIds.size === requiredProductIds.size) {
                        toastr.success('🎉 All products scanned. Ready to scan Order QR!');
                        orderBarcodeInput.disabled = false;
                        orderBarcodeInput.style.display = 'block';
                        setTimeout(() => orderBarcodeInput.focus(), 300);
                    }
                } else {
                    toastr.error(`❌ SKU ${scanned} not found in this order.`);
                }
            }, 200);
        });

        // --- Order QR Scan for Fulfillment ---
        let orderBuffer = '';
        let orderTimer;

        document.addEventListener('keydown', function(e) {
            if (!orderModal.classList.contains('show') || orderBarcodeInput.disabled) return;

            if (e.key.length === 1) {
                orderBuffer += e.key;
            }

            clearTimeout(orderTimer);
            orderTimer = setTimeout(() => {
                const scannedOrder = orderBuffer.trim();
                orderBuffer = '';
                orderBarcodeInput.value = '';

                if (!selectedOrderId || scannedOrder !== selectedOrderId.toString()) {
                    toastr.error('❌ Order QR does not match.');
                    return;
                }

                if (confirm(`Mark order ${scannedOrder} as fulfilled?`)) {
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
                                toastr.success('✅ Order fulfilled successfully!');
                                location.reload();
                            } else {
                                toastr.error(data.message || '❌ Fulfillment failed.');
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            toastr.error('❌ Fulfillment request error.');
                        });
                }
            }, 200);
        });
    });
</script> --}}

{{-- <script>
document.addEventListener('DOMContentLoaded', function () {
    const scanInput = document.getElementById('qr-scan-input');
    const manualInput = document.getElementById('manual-order-input');
    const manualBtn = document.getElementById('manualOpenBtn');
    const orderModal = document.getElementById('orderModal');
    const barcodeImage = document.getElementById('barcodeImage');
    const barcodeContainer = document.getElementById('barcodeContainer');
    const productBarcodeInput = document.getElementById('product-barcode-scan');
    const orderBarcodeInput = document.getElementById('barcode-scan-input');

    let selectedOrderId = null;
    let requiredProductIds = new Set();
    let scannedProductIds = new Set();

    // --- 1. Keep focusing scan input on dashboard ---
    setInterval(() => {
        if (document.activeElement !== manualInput && !orderModal.classList.contains('show')) {
            scanInput.focus();
        }
    }, 500);

    // --- 2. Manual open ---
    manualBtn.addEventListener('click', () => {
        const orderNumber = manualInput.value.trim();
        if (orderNumber) fetchAndShowOrder(orderNumber);
        else alert('Enter a valid order number.');
    });

    // --- 3. Scan from dashboard ---
    let scanTimeout;
    scanInput.addEventListener('input', () => {
        clearTimeout(scanTimeout);
        scanTimeout = setTimeout(() => {
            const scannedOrder = scanInput.value.trim();
            scanInput.value = '';
            if (scannedOrder) fetchAndShowOrder(scannedOrder);
        }, 300);
    });

    // --- 4. Fetch order and populate modal ---
    function fetchAndShowOrder(orderNumber) {
        fetch(`/admin/Accuracy-checker/orders/ajax/${orderNumber}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    selectedOrderId = data.order.order_number;
                    requiredProductIds = new Set();
                    scannedProductIds = new Set();

                    // Set modal data
                    document.getElementById('modalOrderNumber').textContent = selectedOrderId;
                    document.getElementById('modalCustomer').textContent = data.order.email;

                    const s = data.shipping_address;
                    document.getElementById('modalShippingAddress').innerHTML = `
                        ${s.name}<br>${s.address1} ${s.address2}<br>
                        ${s.city} ${s.zip}<br>${s.country}<br>Phone: ${s.phone}`;

                    const tbody = document.getElementById('modalItemsTable');
                    tbody.innerHTML = '';

                    data.items.forEach(item => {
                        requiredProductIds.add(item.product_id.toString());
                        tbody.insertAdjacentHTML('beforeend', `
                            <tr>
                                <td>${item.name}</td>
                                <td>${item.quantity}</td>
                                <td>${item.price}</td>
                                <td>
                                    <img src="${item.barcode_base64}" alt="Product Barcode"
                                        class="product-barcode"
                                        style="height: 60px;"
                                        data-product-id="${item.product_id}"
                                        data-order-id="${selectedOrderId}">
                                </td>
                            </tr>
                        `);
                    });

                    // Initially hide barcode image and disable order scan input
                    barcodeImage.src = '/barcode/' + selectedOrderId;
                    barcodeContainer.style.display = 'none';
                    orderBarcodeInput.style.display = 'none';
                    orderBarcodeInput.disabled = true;

                    new bootstrap.Modal(orderModal).show();
                } else {
                    alert(data.message || 'Order not found.');
                }
            })
            .catch(() => alert('Error loading order.'));
    }

    // --- 5. Track modal open for focusing ---
    new MutationObserver(() => {
        if (orderModal.classList.contains('show')) {
            setTimeout(() => productBarcodeInput.focus(), 300);
        }
    }).observe(orderModal, { attributes: true });

    // --- 6. Product barcode scanner ---
    let productBuffer = '', productTimer;
    document.addEventListener('keydown', function (e) {
        if (!orderModal.classList.contains('show')) return;

        if (e.key.length === 1) productBuffer += e.key;

        clearTimeout(productTimer);
        productTimer = setTimeout(() => {
            const scanned = productBuffer.trim();
            productBuffer = '';
            productBarcodeInput.value = '';

            if (!scanned) return;

            const matched = document.querySelector(`img[data-product-id="${scanned}"]`);
            if (matched) {
                const productId = matched.dataset.productId;
                const orderId = matched.dataset.orderId;

                if (!scannedProductIds.has(productId)) {
                    scannedProductIds.add(productId);
                    fetch(`/admin/Accuracy-checker/product-stock/${productId}/${orderId}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                toastr.success(`✅ ${data.product_title}<br>Stock: ${data.stock}`);
                            } else {
                                toastr.error(data.message || '❌ Stock check failed');
                            }
                        })
                        .catch(() => toastr.error('⚠️ Shopify error'));
                } else {
                    toastr.warning('⚠️ Product already scanned.');
                }

                if (scannedProductIds.size === requiredProductIds.size) {
                    toastr.success('🎉 All products scanned. Ready to scan order QR!');
                    barcodeContainer.style.display = 'block';
                    orderBarcodeInput.style.display = 'block';
                    orderBarcodeInput.disabled = false;
                    setTimeout(() => orderBarcodeInput.focus(), 300);
                }
            } else {
                toastr.error(`❌ SKU ${scanned} not in order.`);
            }
        }, 200);
    });

    // --- 7. Final QR order scan for fulfillment ---
    let orderBuffer = '', orderTimer;
    document.addEventListener('keydown', function (e) {
        if (!orderModal.classList.contains('show') || orderBarcodeInput.disabled) return;

        if (e.key.length === 1) orderBuffer += e.key;

        clearTimeout(orderTimer);
        orderTimer = setTimeout(() => {
            const scanned = orderBuffer.trim();
            orderBuffer = '';
            orderBarcodeInput.value = '';

            if (scanned !== selectedOrderId) {
                toastr.error('❌ Order QR does not match.');
                return;
            }

            if (confirm(`Mark order ${scanned} as fulfilled?`)) {
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
                        toastr.success('✅ Order fulfilled!');
                        location.reload();
                    } else {
                        toastr.error(data.message || '❌ Fulfillment failed');
                    }
                })
                .catch(() => toastr.error('❌ Fulfillment error'));
            }
        }, 200);
    });
});
</script> --}}

<script>
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
</script>
