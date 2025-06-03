{{-- Extends layout --}}
@extends('admin.layouts.app')

{{-- Content --}}
@section('content')

<div class="container-fluid">
    <div class="row page-titles mx-0 mb-3">
        <div class="col-sm-6 p-0">
            <div class="welcome-text">
                <h4>Loyalities</h4>
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
                    <form action="{{ route('admin.loyalities.index') }}" method="get">
                    @csrf
                        <div class="row">
                            <div class="mb-3 col-md-3">
                                <input type="search" name="order_number" class="form-control" placeholder="Order Number" value="{{ old('order_number', request()->input('order_number')) }}">
                            </div>
                            <div class="mb-3 col-md-3">
                                <input type="search" name="customer_email" class="form-control" placeholder="Customer Email" value="{{ old('customer_email', request()->input('customer_email')) }}">
                            </div>
                            <div class="mb-3 col-md-3" >
                                <select name="status" class="form-control">
                                    <option value="">Choose Status</option>
                                    <option value="pending" {{ request()->input('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="completed" {{ request()->input('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                                </select>
                            </div>
                            <div class="mb-3 col-md-3">
                                <input type="submit" name="search" value="search" class="btn btn-primary me-2"> 
                                <a href="{{ route('admin.loyalities.index') }}" class="btn btn-danger">Reset</a>
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
                    <h4 class="card-title">Loyalities</h4>
                </div>
                <div class="pe-4 ps-4 pt-2 pb-2">
                    <div class="table-responsive">
                        <table class="table table-responsive-lg mb-0">
                            <thead>
                                <tr>
                                    <th> <strong> S.no </strong> </th>
                                    <th> <strong> Order Number </strong> </th>
                                    <th> <strong> Customer Email </strong> </th>
                                    <th> <strong> Transaction ID </strong> </th>
                                    <th> <strong> Status </strong> </th>
                                    <th> <strong> Updated </strong> </th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $sNo = ($loyalities->currentPage() - 1) * $loyalities->perPage() + 1;
                                @endphp
                                @forelse ($loyalities as $loyality)
                                    <tr>
                                        <td> {{ $sNo++; }}</td>
                                        <td> {{ $loyality->order_number }} </td>
                                        <td> {{ $loyality->customer_email }} </td>
                                        <td> {{ $loyality->transaction_external_id }} </td>
                                        <td> {{ $loyality->status }} </td>
                                        <td> {{ date(config('Reading.date_time_format'), strtotime($loyality->updated_at)) }} </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">
                                            <p>Records Not Found</p>
                                        </td>
                                    </tr>
                                @endforelse

                            </tbody>
                        </table>
                    </div>
                </div>
                @if($loyalities->hasPages())
                <div class="card-footer">
                    {{ $loyalities->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>

</div>

@endsection