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
                    <li class="breadcrumb-item active">Dispenser</li>
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
                        <form method="GET" action="{{ route('dispenser_orders.index') }}" class="row g-2 align-items-end">
                            {{-- Search --}}
                            <div class="col-md-3">
                                <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                                    placeholder="Search by Name, Email or Order Number">
                            </div>

                            {{-- Filter button --}}
                            <div class="col-md-1 d-grid">
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </div>

                            {{-- Clear button --}}
                            <div class="col-md-1 d-grid">
                                <a href="{{ route('dispenser_orders.index') }}" class="btn btn-secondary">Clear</a>
                            </div>
                            @role('Dispenser')
                                <div class="col-md-3 ms-auto d-grid align-items-end"> <a
                                        href="{{ route('dispenser.batches.list') }}" class="btn btn-info">Dispensed Batches</a>
                                </div>
                            @endrole
                        </form>

                    </div>

                </div>
            </div>

        </div>

        <div class="row">
            <div class="col-12">
                <form action="{{ route('orders.dispensed') }}" method="POST"> @csrf

                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Approved Orders for Dispensing</strong>
                            <div id="dispenseSelectedWrapper" style="display: none;">
                                <button type="submit" class="btn btn-success">Dispense Selected</button>
                            </div>
                        </div>
                        <div class="card-body table-responsive">
                            @if ($orders->isEmpty())
                                <p class="text-muted">No approved orders available for dispensing.</p>
                            @else
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            @role('Dispenser')
                                                <th><input type="checkbox" id="select-all"></th>
                                            @endrole
                                            <th>Order Number</th>
                                            <th>Email</th>
                                            <th>Total Price {{ config('Site.currency') }}</th>
                                            <th>Items Count</th>
                                            <th>Item Names</th>
                                            <th>Store Name</th>
                                            <th>Created At</th>
                                            @role('Dispenser')
                                                <th>Action</th>
                                            @endrole
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($orders as $order)
                                            <tr>
                                                @role('Dispenser')
                                                    <td>
                                                        <input type="checkbox" name="order_ids[]"
                                                            value="{{ $order->order_number }}" class="order-checkbox">
                                                    </td>
                                                @endrole
                                                <td>{{ $order->order_number }}</td>
                                                <td style="max-width: 200px; word-wrap: break-word;">{{ $order->email }}
                                                </td>
                                                <td>{{ number_format($order->total_price, 2) }}</td>
                                                <td>{{ $order->total_quantity }}</td>
                                                <td>
                                                    {{ collect($order->decoded_order_data['line_items'] ?? [])->map(fn($item) => $item['title'] . ' × ' . $item['quantity'])->join(', ') }}
                                                </td>
                                                <td>{{ ucfirst($order->store->name ?? 'NA') }}</td>


                                                <td>{{ $order->created_at->format(config('Reading.date_time_format')) }}
                                                </td>
                                                @role('Dispenser')
                                                    <td>
                                                        <div class="d-flex">
                                                            <a href="{{ route('dispenser_orders.view', $order->id) }}"
                                                                class="btn btn-primary btn-sm">
                                                                <i class="fa fa-eye" aria-hidden="true"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                @endrole
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="mt-1">
            {!! $orders->appends(request()->query())->links('pagination::bootstrap-5') !!}
        </div>

        <script>
            function toggleDispenseButton() {
                const anyChecked = document.querySelectorAll('.order-checkbox:checked').length > 0;
                document.getElementById('dispenseSelectedWrapper').style.display = anyChecked ? 'block' : 'none';
            }

            document.addEventListener('DOMContentLoaded', function() {
                // Watch select-all
                document.getElementById('select-all')?.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('.order-checkbox');
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    toggleDispenseButton();
                });

                // Watch individual checkboxes
                document.querySelectorAll('.order-checkbox').forEach(cb => {
                    cb.addEventListener('change', toggleDispenseButton);
                });
            });
        </script>

    @endsection
