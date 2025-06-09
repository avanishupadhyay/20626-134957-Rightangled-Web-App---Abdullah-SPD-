@extends('admin.layouts.app')

@section('content')
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
                    <li class="breadcrumb-item active">Order List</li>
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
                        <form method="GET" action="{{ route('orders.index') }}" class="row g-2 align-items-end">
                            {{-- Search --}}
                            <div class="col-md-4">
                                <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                                    placeholder="Search by Name, Email or Order Number">
                            </div>

                            {{-- Status dropdown (dynamically loaded) --}}
                            <div class="col-md-2">
                                <select name="financial_status" class="form-select">
                                    <option value="">All Financial Status</option>
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status }}"
                                            {{ request('financial_status') == $status ? 'selected' : '' }}>
                                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <input type="text" name="date_range" id="date_range" value="{{ request('date_range') }}"
                                    class="form-control" placeholder="Select date range" autocomplete="off" />
                            </div>

                            {{-- Filter button --}}
                            <div class="col-md-1 d-grid">
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </div>

                            {{-- Clear button --}}
                            <div class="col-md-1 d-grid ms-1">
                                <a href="{{ route('orders.index') }}" class="btn btn-secondary">Clear</a>
                            </div>
                        </form>
                    </div>

                </div>
            </div>

        </div>

        <div class="row">
            <!-- Column starts -->
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Order</h4>
                    </div>
                    <div class="pe-4 ps-4 pt-2 pb-2">
                        <div class="table-responsive">
                            <table class="table table-responsive-lg mb-0">
                                {{-- <thead class="text-secondary"> --}}
                                <tr>
                                    <th>#</th>
                                    <th>Order Number</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Total Price {{ config('Site.currency') }}</th>
                                    <th>Financial Status</th>
                                    <th>Fulfillment Status</th>
                                    <th>Created At</th>
                                    <th>Action</th>

                                </tr>
                                {{-- </thead> --}}
                                <tbody>
                                    @forelse ($orders as $index => $order)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $order->order_number }}</td>
                                            <td>{{ $order->name }}</td>
                                            <td>{{ $order->email }}</td>
                                            <td>{{ number_format($order->total_price, 2) }}</td>
                                            <td>
                                                @php
                                                    $status = $order->financial_status;
                                                    $badgeClass = match ($status) {
                                                        'paid' => 'success',
                                                        'authorized', 'partially_paid' => 'info',
                                                        'pending' => 'warning',
                                                        'expired' => 'danger',
                                                        default => 'secondary',
                                                    };
                                                @endphp

                                                <span class="badge bg-{{ $badgeClass }}">
                                                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                                                </span>
                                            </td>

                                            <td>{{ ucfirst($order->fulfillment_status) ?? 'NA' }}</td>
                                            <td>{{ $order->created_at->format(config('Reading.date_time_format')) }}</td>
                                            {{-- <td>
                                               <a class="btn btn-primary btn-sm" href="{{ route('orders.view', $order->id) }}"><i
                                                    class="fa-solid fa-pen-to-square" style="width: 100px"></i> </a>
                                            </td> --}}
                                            <td class="d-flex">
                                                <a href="{{ route('orders.view', $order->id) }}"
                                                    class="btn btn-primary btn-sm"><i class="fa-solid fa-pen-to-square"
                                                        style="width: 100px"></i></a>

                                                <a href="{{ route('orders.downloadPDF', $order->id) }}"
                                                    class="btn btn-sm btn-danger ms-2" target="_blank" title="Download PDF">
                                                    <i class="fas fa-file-pdf"></i>
                                                </a>

                                            </td>

                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center">No orders found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-1">
            {!! $orders->links('pagination::bootstrap-5') !!}
        </div>
        <script>
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
        </script>
    @endsection
