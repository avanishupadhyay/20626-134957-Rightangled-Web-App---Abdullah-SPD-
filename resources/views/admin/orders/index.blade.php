{{-- Extends layout --}}
@extends('admin.layouts.app')

{{-- Content --}}
@section('content')

<style>
    .details-row {
        background-color: #f9f9f9;
        border-top: 1px solid #ddd;
    }
    .toggle-details-btn {
        background: none;
        border: none;
        cursor: pointer;
    }
    .toggle-details-btn i {
        font-size: 16px;
        color: #007bff;
    }
</style>

<div class="container-fluid">
    <div class="row page-titles mx-0 mb-3">
        <div class="col-sm-6 p-0">
            <div class="welcome-text">
                <h4>Orders</h4>
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
                    <form action="{{ route('admin.orders.index') }}" method="get">
                    @csrf
                        <div class="row">
                            <div class="mb-3 col-md-3">
                                <input type="search" name="order_number" class="form-control" placeholder="Order Number" value="{{ old('order_number', request()->input('order_number')) }}">
                            </div>
                            <div class="mb-3 col-md-3">
                                <input type="search" name="customer_id" class="form-control" placeholder="Customer ID" value="{{ old('customer_id', request()->input('customer_id')) }}">
                            </div>
                            <div class="mb-3 col-md-3">
                                <input type="search" name="customer_email" class="form-control" placeholder="Customer Email" value="{{ old('customer_email', request()->input('customer_email')) }}">
                            </div>
                            <div class="mb-3 col-md-3">
                                <input type="submit" name="search" value="search" class="btn btn-primary me-2"> 
                                <a href="{{ route('admin.orders.index') }}" class="btn btn-danger">Reset</a>
                            </div>
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
                    <h4 class="card-title">Orders</h4>
                </div>
                <div class="pe-4 ps-4 pt-2 pb-2">
                    <div class="table-responsive">
                        <table class="table table-responsive-lg mb-0">
                            <thead>
                                <tr>
                                    <th> <strong> S.no </strong> </th>
                                    <th> <strong> Order Number </strong> </th>
                                    <th> <strong> Customer ID </strong> </th>
                                    <th> <strong> Customer Email </strong> </th>
                                    <th> <strong> Loyalty </strong> </th>
                                    <th> <strong> Status </strong> </th>
                                    <th> <strong> Created </strong> </th>
                                    <th> <strong> More </strong> </th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $sNo = ($orders->currentPage() - 1) * $orders->perPage() + 1;
                                @endphp
                                @forelse ($orders as $order)
                                    <tr>
                                        <td> {{ $sNo++; }}</td>
                                        <td> {{ $order->order_number }} </td>
                                        <td> {{ $order->customer_id }} </td>
                                        <td> {{ $order->customer_email }} </td>
                                        <td> {{ $order->m7_marketing ? 'Subscribed' : 'Not Subscribed' }} </td>
                                        <td> {{ $order->status }} </td>
                                        <td> {{ date(config('Reading.date_time_format'), strtotime($order->created_at)) }} </td>
                                        <td>
                                            <button class="toggle-details-btn" data-row-id="details-{{ $order->id }}">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr id="details-{{ $order->id }}" class="details-row" style="display: none;">
                                        <td colspan="8">
                                            <p>Customer Name: {{ $order->customer_name }}</p>
                                            <p>Total Amount: {{ $order->total_amount }}</p>
                                            <p>Currency: {{ $order->currency }}</p>
                                            @if($order->transaction_external_id)
                                            <p>Transaction ID: {{ $order->transaction_external_id }}</p>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">
                                            <p>Records Not Found</p>
                                        </td>
                                    </tr>
                                @endforelse

                            </tbody>
                        </table>
                    </div>
                </div>
                @if($orders->hasPages())
                <div class="card-footer">
                    {{ $orders->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection
@section('custom_js_scripts')
<script>
    jQuery(this).ready(function(){
        jQuery('.toggle-details-btn').on('click',function(e){
            let rowId = jQuery(this).data('row-id');
            jQuery('#'+rowId).slideToggle('slow');
        });
    });
</script>
@endsection