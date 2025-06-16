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
                            <div class="col-md-3 ms-auto d-grid align-items-end"> <a href="{{ route('dispenser.batches.list') }}"
                                    class="btn btn-info">Dispensed Batches</a> </div>
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
                            <button type="submit" class="btn btn-primary">Dispense Selected</button>
                        </div>
                        <div class="card-body table-responsive">
                            @if ($orders->isEmpty())
                                <p class="text-muted">No approved orders available for dispensing.</p>
                            @else
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="select-all"></th>
                                            <th>Order Number</th>
                                            <th>Email</th>
                                            <th>Total Price</th>
                                            <th>Items Count</th>
                                            <th>Item Names</th>
                                            <th>Created At</th>
                                            <th>Action</th>

                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($orders as $order)
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="order_ids[]"
                                                        value="{{ $order->order_number }}" class="order-checkbox">
                                                </td>
                                                <td>{{ $order->order_number }}</td>
                                                <td style="max-width: 200px; word-wrap: break-word;">{{ $order->email }}
                                                </td>
                                                <td>{{ number_format($order->total_price, 2) }}</td>
                                                <td>{{ $order->total_quantity }}</td>
                                                <td>
                                                    {{ collect($order->decoded_order_data['line_items'] ?? [])->map(fn($item) => $item['title'] . ' × ' . $item['quantity'])->join(', ') }}
                                                </td>

                                                <td>{{ \Carbon\Carbon::parse($order->created_at)->format('d/m/Y H:i') }}
                                                </td>
                                                <td class="d-flex">
                                                    <a href="{{ route('dispenser_orders.view', $order->id) }}"
                                                        class="btn btn-primary btn-sm">
                                                        <i class="fa fa-eye" aria-hidden="true"></i>
                                                    </a>
                                                </td>
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
        {{-- <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize daterangepicker
                $('#date_range').daterangepicker({
                    autoUpdateInput: false,
                    locale: {
                        cancelLabel: 'Clear',
                        format: 'YYYY-MM-DD'
                    },
                    opens: 'left',
                    maxDate: moment(), // Optional: disallow future dates
                });

                $('#date_range').on('apply.daterangepicker', function(ev, picker) {
                    $(this).val(picker.startDate.format('YYYY-MM-DD') + ' to ' + picker.endDate.format(
                        'YYYY-MM-DD'));
                });

                $('#date_range').on('cancel.daterangepicker', function(ev, picker) {
                    $(this).val('');
                });

                // If there is already a date_range value, set the picker accordingly
                @if (request('date_range'))
                    let dates = "{{ request('date_range') }}".split(' to ');
                    if (dates.length === 2) {
                        $('#date_range').data('daterangepicker').setStartDate(dates[0]);
                        $('#date_range').data('daterangepicker').setEndDate(dates[1]);
                        $('#date_range').val("{{ request('date_range') }}");
                    }
                @endif
            });
        </script> --}}
        <script>
            document.getElementById('select-all')?.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.order-checkbox');
                checkboxes.forEach(checkbox => checkbox.checked = this.checked);
            });
        </script>
    @endsection
