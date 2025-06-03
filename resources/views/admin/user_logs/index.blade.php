{{-- Extends layout --}}
@extends('admin.layouts.app')

{{-- Content --}}
@section('content')

<div class="container-fluid">
    <div class="row page-titles mx-0 mb-3">
        <div class="col-sm-6 p-0">
            <div class="welcome-text">
                <h4>User Logs</h4>
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
                    <form action="{{ route('admin.user_logs.index') }}" method="get">
                    @csrf
                        <div class="row">
                            <div class="mb-3 col-md-3">
                                <input type="search" name="customer_id" class="form-control" placeholder="Customer ID" value="{{ old('customer_id', request()->input('customer_id')) }}">
                            </div>
                            <div class="mb-3 col-md-3">
                                <input type="search" name="email" class="form-control" placeholder="Email" value="{{ old('email', request()->input('email')) }}">
                            </div>
                            <div class="mb-3 col-md-6">
                                <input type="submit" name="search" value="search" class="btn btn-primary me-2"> 
                                <a href="{{ route('admin.user_logs.index') }}" class="btn btn-danger">Reset</a>
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
                    <h4 class="card-title">Logs</h4>
                </div>
                <div class="pe-4 ps-4 pt-2 pb-2">
                    <div class="table-responsive">
                        <table class="table table-responsive-lg mb-0">
                            <thead>
                                <tr>
                                    <th> <strong> S.no </strong> </th>
                                    <th> <strong> Customer ID </strong> </th>
                                    <th> <strong> Action </strong> </th>
                                    <th> <strong> Email </strong> </th>
                                    <th> <strong> Created </strong> </th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    //$sNo = ($user_logs->currentPage() - 1) * $user_logs->perPage() + 1;  /* Increasing Serial Number */
                                    $sNo = ($user_logs->total() - ($user_logs->currentPage() - 1) * $user_logs->perPage()); /* Decreasing Serial Number */
                                @endphp
                                @forelse ($user_logs as $user_log)
                                    <tr>
                                        <td> {{ $sNo--; }}</td>
                                        <td> {{ $user_log->customer_id }} </td>
                                        <td> {{ $user_log->action }} </td>
                                        <td> {{ $user_log->email }} </td>
                                        <td> {{ date(config('Reading.date_time_format'), strtotime($user_log->created_at)) }} </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">
                                            <p>Records Not Found</p>
                                        </td>
                                    </tr>
                                @endforelse

                            </tbody>
                        </table>
                    </div>
                </div>
                @if($user_logs->hasPages())
                <div class="card-footer">
                    {{ $user_logs->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>

</div>

@endsection