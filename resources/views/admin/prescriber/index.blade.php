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
                    <li class="breadcrumb-item active">Order List</li>
                </ol>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <span>Total Pending</span><br>
                        <h3>{{ $counts['total_pending'] ?? '' }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <span>Approved</span><br>
                        <h3>{{ $counts['total_approved'] ?? '' }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <span>On Hold</span><br>
                        <h3>{{ $counts['total_on_hold'] ?? '' }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <span>Rejected</span><br>
                        <h3>{{ $counts['total_rejected'] ?? '' }}</h3>
                    </div>
                </div>
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
                        <form method="GET" action="{{ route('prescriber_orders.index') }}"
                            class="row g-2 align-items-end">
                            {{-- Search --}}
                            <div class="col-md-3">
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
                            {{-- Order Type dropdown --}}
                            <div class="col-md-2">

                                <select name="filter_type" class="form-select">
                                    <option value="">-- Select Filter --</option>
                                    <option value="new" {{ request('filter_type') == 'new' ? 'selected' : '' }}>New
                                    </option>
                                    <option value="repeat" {{ request('filter_type') == 'repeat' ? 'selected' : '' }}>Repeat
                                    </option>
                                    <option value="international"
                                        {{ request('filter_type') == 'international' ? 'selected' : '' }}>International
                                    </option>
                                    <option value="all" {{ request('filter_type') == 'all' ? 'selected' : '' }}>All
                                    </option>
                                </select>
                            </div>



                            {{-- Filter button --}}
                            <div class="col-md-1 d-grid">
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </div>

                            {{-- Clear button --}}
                            <div class="col-md-1 d-grid">
                                <a href="{{ route('prescriber_orders.index') }}" class="btn btn-secondary">Clear</a>
                            </div>
                        </form>
                    </div>

                </div>
            </div>

        </div>

        <div class="row">
            <div class="col-12">
                <div class="card w-100">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Order</h4>
                    </div>
                    <div class="card-body p-3">
                        <!-- Responsive Table Container -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Order Number</th>
                                        <th>Email</th>
                                        <th>Total Price {{ config('Site.currency') }}</th>
                                        <th>Financial Status</th>
                                        <th>Fulfillment Status</th>
                                        {{-- <th>Status</th>
                                        <th>Prescriber</th> 
                                        <th>Last Action</th>--}}
                                        <th>Created At</th>
                                        @role('Prescriber')
                                        <th>Action</th>
                                        @endrole
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($orders as $index => $order)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $order->order_number }}</td>
                                            <td class="text-break" style="min-width: 150px;">{{ $order->email }}</td>
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
                                            {{-- <td>{{ $order->orderaction->decision_status ?? 'N/A' }}</td>
                                            <td>{{ $order->orderaction->user->name ?? 'N/A' }}</td> --}}
                                            {{-- <td>{{ $order->orderaction?->updated_at?->format(config('Reading.date_time_format')) ?? 'N/A' }} --}}
                                           
                                            <td>{{ $order->created_at->format(config('Reading.date_time_format')) }}</td>
                                            @role('Prescriber')
                                            <td>
                                                <div class="d-flex">
                                                    <a href="{{ route('prescriber_orders.view', $order->id) }}"
                                                        class="btn btn-primary btn-sm">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </a>
                                                </div>
                                            </td>
                                            @endrole
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="11" class="text-center">No orders found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div> <!-- End .table-responsive -->
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-1">
            {!! $orders->appends(request()->query())->links('pagination::bootstrap-5') !!}
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
