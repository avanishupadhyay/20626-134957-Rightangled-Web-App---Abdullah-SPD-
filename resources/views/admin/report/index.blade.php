@extends('admin.layouts.app')

<style>
    .little-txt {
        font-size: 10px;
    }

    /* .boxes {
        border: 1px solid black;
        padding: 10px;
        box-shadow: 1px 1px 4px gray;
    } */

    select.form-select {
        width: auto;
    }

    input.form-control {
        width: auto;
    }

    .btn.btn-primary {
        width: auto;
    }

    .btn.btn-secondary {
        width: auto;
    }
</style>
@section('content')
    <div class="container-fluid">
        <div class="row text-end">
            <div class="col-sm-12">
                {{-- <button class="btn btn-primary">Export Report <i class="fa-solid fa-file-export"></i></button> --}}
                <a href="{{ route('admin.reports.export', request()->query()) }}" class="btn btn-primary">
                    Export Report <i class="fa-solid fa-file-export"></i>
                </a>
            </div>
        </div><br>
        <div class="row d-flex justify-content-between">
            <div class="col-md-3">
                <div class="card p-3">
                    <div class="d-flex justify-content-between">
                        <span>Total Prescription</span>
                        <span><i class="fa-solid fa-circle-check"></i></span>
                    </div>
                    <div>
                        <strong>{{ ($roleWiseCounts['Prescriber'] ?? '0') }}</strong><br>
                        {{-- <span class="color-gray little-txt">+5% vs last month</span> --}}
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3">
                    <div class="d-flex justify-content-between">
                        <span>Total Checker</span>
                        <span><i class="fa-solid fa-circle-xmark"></i></i></span>
                    </div>
                    <div>
                        <strong>{{ ($roleWiseCounts['Checker'] ?? '0') }}</strong><br>
                        {{-- <span class="color-gray little-txt">+5% vs last month</span> --}}
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3">
                    <div class="d-flex justify-content-between">
                        <span>Total Despenser</span>
                        <span><i class="fa-solid fa-box"></i></span>
                    </div>
                    <div>
                        <strong>{{ ($roleWiseCounts['Dispenser'] ?? '0') }}</strong><br>
                        {{-- <span class="color-gray little-txt">+5% vs last month</span> --}}
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3">
                    <div class="d-flex justify-content-between">
                        <span>Total ACT</span>
                        <span><i class="fa-solid fa-circle-check"></i></span>
                    </div>
                    <div>
                        <strong>{{ ($roleWiseCounts['ACT'] ?? '0') }}</strong><br>
                        {{-- <span class="color-gray little-txt">+5% vs last month</span> --}}
                    </div>
                </div>
            </div>
        </div><br>
        {{-- <hr> --}}
        <div class="card">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.report') }}" class="row g-2 align-items-end">
                    <div class="row d-flex gap-2 align-items-center">
                        <span class="col-md-2">
                            <i class="fa fa-filter"></i>&nbsp;Filter :&nbsp;&nbsp;
                        </span>

                        {{-- Date Range Filter  --}}
                        {{-- <input type="date" class="form-control" name="from" value="{{ request('from') }}">

                            <input type="date" class="form-control" name="to" value="{{ request('to') }}"> --}}

                        <input type="text" id="date_range" class="form-control" placeholder="Select Date Range" readonly>
                        <!-- Hidden inputs to submit with form -->
                        <input type="hidden" name="from" id="from_date" value="{{ request('from') }}">
                        <input type="hidden" name="to" id="to_date" value="{{ request('to') }}">

                        {{-- Search SKU Filter  --}}
                        <input type="text" class="form-control" placeholder="Search SKU" name="sku"
                            value="{{ request('sku') }}">

                        {{-- Select Store Filter --}}
                        <select class="form-select" name="store">
                            <option value="">Select Store</option>
                            @foreach ($store as $key => $value)
                                <option value="{{ $value['id'] }}"
                                    {{ request('store') == $value['id'] ? 'selected' : '' }}>
                                    {{ $value['name'] }}
                                </option>
                            @endforeach
                        </select>

                        {{-- Select User Filter --}}
                        <select class="form-select" name="user">
                            <option value="">Select User</option>
                            @foreach ($users as $key => $value)
                                <option value="{{ $value['id'] }}" {{ request('user') == $value['id'] ? 'selected' : '' }}>
                                    {{ ucfirst($value['name']) }}
                                </option>
                            @endforeach

                        </select>

                        {{-- Select Role Filter --}}
                        <select class="form-select" name="role">
                            <option value="">Select Role</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role }}" {{ request('role') == $role ? 'selected' : '' }}>
                                    {{ $role }}
                                </option>
                            @endforeach
                        </select>

                        {{-- Select Report Type Filter --}}
                        {{-- <select class="form-select" name="report_type">
                                <option value="">Report Type</option>
                            </select> --}}

                        {{-- Select Metrics Filter --}}
                        <select class="form-select" name="metrics">
                            <option value="">Metrics</option>
                            <option value="approved" {{ request('metrics') == 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="rejected" {{ request('metrics') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                            <option value="on_hold" {{ request('metrics') == 'on_hold' ? 'selected' : '' }}>On Hold</option>
                            {{-- <option value="SKUs" {{ request('metrics') == 'SKUs' ? 'selected' : '' }}>SKUs</option>
                            <option value="fulfillment rates" {{ request('metrics') == 'fulfillment rates' ? 'selected' : '' }}>Fulfillment Rates</option> --}}
                        </select>

                        <button class="btn btn-primary">Apply Filter</button>
                        <a href="{{ route('admin.report') }}" class="btn btn-secondary">Reset</a>

                    </div>
                </form>
            </div>
        </div>
        {{-- <hr>
        <div class="row">
            <div class="col-md-6">
                <span class="card p-3">Prescription Actions Over Time (Weekly)</span>
            </div>
            <div class="col-md-6">
                <span class="card p-3">Overall Fulfillment Rate (Quarterly)</span>
            </div>
        </div> --}}
        <hr>
        <div class="card">
            <div class="card-body overflow-scroll">
                <h5>Detailed Report Data</h5>
                {{-- <table class="table table-bordered table-responsive">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Prescription ID</th>
                            <th>SKU</th>
                            <th>Store</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Clinical Notes Snippet</th>
                            <th>Compliance Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $key => $value)
                            @php
                                $order_data = json_decode($value['order_data'], true);
                                $sku = [];
                                $sku_txt = '';
                                if (isset($order_data['line_items']) && is_array($order_data['line_items'])) {
                                    foreach ($order_data['line_items'] as $skey => $svalue) {
                                        if (isset($svalue['sku'])) {
                                            $sku[] = $svalue['sku'];
                                        }
                                    }
                                    $sku_txt = implode(',', $sku);
                                }
                                $prescription_data = getPrescriptionData($value['order_number']);
                                $audit_data = getAuditData($value['order_number']);
                                $prescription_id = null;
                                $action = null;
                                $clinical_reasoning = null;

                                if (!empty($prescription_data)) {
                                    $prescription_id = $prescription_data['prescriber_id'] ?? null;
                                    $clinical_reasoning = $prescription_data['clinical_reasoning'] ?? null;
                                }

                                if (!empty($audit_data)) {
                                    $action = $audit_data['action'] ?? null;
                                }

                            @endphp
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($order_data['created_at'])->format('Y-m-d H:i') }}</td>
                                <td>{{ $prescription_id }}</td>
                                <td>{{ $sku_txt }}</td>
                                <td> - </td>
                                <td>{{ $order_data['customer']['first_name'] . ' ' . $order_data['customer']['last_name'] }}
                                </td>
                                <td>{{ $action }}</td>
                                <td>{{ $clinical_reasoning }}</td>
                                <td>Compliance Status</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table> --}}
                <table class="table table-bordered table-responsive">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Action</th>
                            <th>Role</th>
                            <th>SKU</th>
                            <th>Clinical Notes Snippet</th>
                            <th>Rejection Reason</th>
                            <th>On Hold Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $key => $value)
                            @php

                                $order_values = getOrderData($value['order_id']);
                             
                                $sku_txt = '';
                                $sku = [];

                                $order_data = json_decode($order_values['order_data'], true);
                                if (isset($order_data['line_items']) && is_array($order_data['line_items'])) {
                                    foreach ($order_data['line_items'] as $skey => $svalue) {
                                        if (isset($svalue['sku'])) {
                                            $sku[] = $svalue['sku'];
                                        }
                                    }
                                    $sku_txt = implode(',', $sku);
                                }

                                $status = '';
                                if($value['decision_status'] == "on_hold") {
                                    $status = 'On Hold';     
                                }else{
                                    $status = ucfirst($value['decision_status']);   
                                }

                            @endphp
                            <tr>
                                <td>{{ $value['order_id'] }}</td>
                                <td>{{ $status }}</td>
                                <td>{{ $value['role'] }}</td>
                                <td>{{ $sku_txt }}</td>
                                <td>{{ ($value['clinical_reasoning'] ?? '-') }}</td>
                                <td>{{ ($value['rejection_reason '] ?? '-') }}</td>
                                <td>{{ ($value['on_hold_reason'] ?? '-') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div>
                    {{ $orders->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
@section('custom_js_scripts')
    <script>
        $(function() {
            let from = '{{ request('from') }}';
            let to = '{{ request('to') }}';

            let displayText = '';
            if (from && to) {
                displayText = moment(from).format('YYYY-MM-DD') + ' - ' + moment(to).format('YYYY-MM-DD');
            }

            $('#date_range').val(displayText);

            $('#date_range').daterangepicker({
                autoUpdateInput: false,
                locale: {
                    format: 'YYYY-MM-DD',
                    cancelLabel: 'Clear'
                }
            });

            $('#date_range').on('apply.daterangepicker', function(ev, picker) {
                $('#from_date').val(picker.startDate.format('YYYY-MM-DD'));
                $('#to_date').val(picker.endDate.format('YYYY-MM-DD'));
                $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format(
                    'YYYY-MM-DD'));
            });

            $('#date_range').on('cancel.daterangepicker', function(ev, picker) {
                $(this).val('');
                $('#from_date').val('');
                $('#to_date').val('');
            });
        });
    </script>
@endsection
