@extends('admin.layouts.app')
    
@section('content')
    <div class="container-fluid">
        <div class="row page-titles mx-0 mb-3">
            <div class="col-sm-6 p-0">
                <div class="welcome-text">
                    <h4>Dashboard</h4>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <div class="row">
                    <div class="col-sm-4 col-md-3 mb-3">
                        <div class="card prod-p-card bg-light-info background-pattern">
                            <div class="card-body">
                                <div class="row align-items-center m-b-0">
                                    <div class="col">
                                        {{-- <h6 class="m-b-5">Total Subscription</h6>
                                        <h3 class="m-b-0">{!! getTotalSubscribeUser() !!}</h3> --}}
                                    </div>
                                    {{-- <div class="col-auto">
                                        <p><a class="btn btn-info" href="{{ route('admin.user_logs.index') }}">View</a></p>
                                    </div> --}}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 col-md-3 mb-3">
                        <div class="card prod-p-card bg-light-success background-pattern">
                            <div class="card-body">
                                <div class="row align-items-center m-b-0">
                                    {{-- <div class="col">
                                        <h6 class="m-b-5">Total Orders</h6>
                                        <h3 class="m-b-0">{!! getTotalUserOrder() !!}</h3>
                                    </div>
                                    <div class="col-auto">
                                        <p><a class="btn btn-success" href="{{ route('admin.orders.index') }}">View</a></p>
                                    </div> --}}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 col-md-3 mb-3">
                        <div class="card prod-p-card bg-light-primary background-pattern">
                            <div class="card-body">
                                <div class="row align-items-center m-b-0">
                                    
                                    {{-- <div class="col-auto">
                                        <p><a class="btn btn-primary" href="{{ route('admin.loyalities.index') }}">View</a></p>
                                    </div> --}}
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-sm-12 recent-signup">
                        <div class="card prod-p-card background-pattern">
                            <div class="card-body">
                                <div class="row align-items-center m-b-0">
                                    {{-- <div class="col">
                                        <h6 class="m-b-5">Recent Sign Up</h6>
                                        
                                        @php
                                            $subscribers = getRecentSubscribers();
                                        @endphp
                                        
                                        <ul>
                                                @forelse($subscribers as $subscriber)
                                                    <li>
                                                        {{ $subscriber['email'] .'-'. $subscriber['customer_id'] .' subscribed on '.date(config('Reading.date_time_format'), strtotime($subscriber['customer_created_at'])) }}
                                                    </li>
                                                @empty
                                                    <li> No Record Found. </li>
                                                @endforelse
                                        </ul>
                                    </div> --}}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
	</div>
@endsection

