@extends('admin.layouts.app')

@section('content')
    <style>
        .equal-height-card {
            min-height: 300px;
            display: flex;
            flex-direction: column;
        }

        .equal-height-card .card-body {
            flex: 1;
            overflow-y: auto;
        }
    </style>
    @php
        $statuses = getOrderDecisionStatus($order->id);
        // dd($statuses);
    @endphp
    <div class="container">
        <div class="row page-titles mx-0 mb-3">
            <div class="col-sm-6 p-0">
                <div class="welcome-text">
                    <h4>Order {{ $orderData['name'] ?? 'Order' }}
                        @if (!empty($orderData['cancelled_at']))
                            <span class="badge bg-danger ms-2">Cancelled</span>
                        @endif
                    </h4>
                </div>
            </div>
            <div class="col-sm-6 p-0 justify-content-sm-end mt-2 mt-sm-0 d-flex">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('orders.index') }}">Orders</a></li>
                    <li class="breadcrumb-item active">Edit</li>
                </ol>
            </div>
        </div>

        <div class="m-3">
            @if (!$statuses['is_cancelled'])
                @if ($statuses['fulfillment_status'] === 'on_hold')
                    <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#releaseHoldModal">Release
                        Hold</button>
                @elseif ($statuses['fulfillment_status'] === 'fulfilled')
                @else
                    @switch($statuses['latest_decision_status'])
                        @case('approved')
                            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#onHoldModal">On Hold</button>
                            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
                        @break

                        @case('rejected')
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal">Approve</button>
                            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#onHoldModal">On Hold</button>
                        @break

                        @default
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal">Approve</button>
                            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
                            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#onHoldModal">On Hold</button>
                    @endswitch
                @endif
            @endif
        </div>
           <form action="{{ route('admin.audit-log.store') }}" method="POST" enctype="multipart/form-data" class="mb-3">
            @csrf
            <input type="hidden" name="order_id" value="{{ $order->order_number }}">

            <div class="row align-items-end mb-3">
                <!-- Details Textarea -->
                <div class="col-md-8">
                    <textarea name="details" id="details" class="form-control" rows="3" placeholder="Enter reason or notes..."></textarea>
                </div>

                <!-- File Upload -->
                <div class="col-md-2 text-center">
                    <label for="file" class="form-label d-block">Attach PDF</label>
                    <label for="file" class="btn btn-outline-secondary">
                        <i class="fa fa-paperclip" style="font-size:20px"></i>
                    </label>
                    <input type="file" name="file" id="file" class="d-none" accept="application/pdf">
                </div>
                <div class="col-md-2 text-center">
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Submit Log
                        </button>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->

        </form>


        <div class="row">
            {{-- Left Card --}}
            <div class="col-md-6">
                <div class="card mb-4 equal-height-card">
                    <div class="card-header">
                        <strong>Fulfillment Status:</strong>
                        <span class="badge bg-{{ $orderData['fulfillment_status'] ? 'success' : 'secondary' }}">
                            {{ ucfirst($order['fulfillment_status'] ?? 'Unfulfilled') }}
                        </span>
                    </div>
                    <div class="card-body">
                        <p><strong>Order Status:</strong>
                            <span class="badge bg-{{ $orderData['financial_status'] == 'paid' ? 'success' : 'warning' }}">
                                {{ ucfirst($orderData['financial_status']) }}
                            </span>
                        </p>

                        {{-- @foreach ($orderData['line_items'] as $item)
                            <div class="row mb-2">
                                <div class="col-md-6">
                                    <strong>Product:</strong> {{ $item['title'] }} ({{ $item['variant_title'] ?? '' }})
                                </div>
                                <div class="col-md-6 text-end">
                                    <strong>£{{ number_format($item['price'], 2) }}</strong> ×
                                    {{ $item['current_quantity'] }}
                                    = <strong>£{{ number_format($item['price'] * $item['current_quantity'], 2) }}</strong>
                                </div>
                            </div>
                        @endforeach --}}
                        @foreach ($orderData['line_items'] as $item)
                            @if ($item['current_quantity'] > 0)
                                <div class="row mb-2">
                                    <div class="col-md-6">
                                        <strong>Product:</strong> {{ $item['title'] }} ({{ $item['variant_title'] ?? '' }})
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <strong>£{{ number_format($item['price'], 2) }}</strong> ×
                                        {{ $item['current_quantity'] }}
                                        =
                                        <strong>£{{ number_format($item['price'] * $item['current_quantity'], 2) }}</strong>
                                    </div>
                                </div>
                            @endif
                        @endforeach

                    </div>
                </div>
            </div>


            @php
                $itemCount = 0;
                foreach ($orderData['line_items'] as $item) {
                    if ($item['current_quantity'] > 0) {
                        $itemCount += $item['current_quantity'];
                    }
                }

                $subtotal = $orderData['current_subtotal_price'] ?? 0;
                $discount = $orderData['current_total_discounts'] ?? 0;
                $shipping = $orderData['current_shipping_price_set']['shop_money']['amount'] ?? 0;
                $tax = $orderData['current_total_tax'] ?? 0;
                $total = $orderData['current_total_price'] ?? 0;

                $balance = $orderData['total_outstanding'] ?? 0;
                $paidAmount = $total - $balance;

                $isPartiallyPaid = strtolower($orderData['financial_status'] ?? '') === 'partially_paid';
            @endphp

            <div class="col-md-6">
                <div class="card mb-4 equal-height-card">
                    <div class="card-header">
                        <strong>{{ ucfirst($orderData['financial_status']) }}</strong>
                    </div>
                    <div class="card-body">

                        <div class="row mb-2">
                            <div class="col-md-6">Subtotal ({{ $itemCount }} item{{ $itemCount !== 1 ? 's' : '' }})
                            </div>
                            <div class="col-md-6 text-end">
                                £{{ number_format($subtotal, 2) }}
                            </div>
                        </div>

                        @if ($discount > 0)
                            <div class="row mb-2">
                                <div class="col-md-6">Discount</div>
                                <div class="col-md-6 text-end text-danger">
                                    -£{{ number_format($discount, 2) }}
                                </div>
                            </div>
                        @endif

                        <div class="row mb-2">
                            <div class="col-md-6">Shipping</div>
                            <div class="col-md-6 text-end">
                                £{{ number_format($shipping, 2) }}
                            </div>
                        </div>

                        @if ($tax > 0)
                            <div class="row mb-2">
                                <div class="col-md-6">Tax</div>
                                <div class="col-md-6 text-end">
                                    £{{ number_format($tax, 2) }}
                                </div>
                            </div>
                        @endif

                        <div class="row">
                            <div class="col-md-6 fw-bold">Total</div>
                            <div class="col-md-6 text-end fw-bold">
                                £{{ number_format($total, 2) }}
                            </div>
                        </div>

                        <hr>

                        <div class="row fw-bold">
                            <div class="col-md-6">Paid</div>
                            <div class="col-md-6 text-end">
                                £{{ number_format($paidAmount, 2) }}
                            </div>
                        </div>



                    </div>
                </div>
            </div>


        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4" style="height: 400px;  overflow-y: auto;">
                    <div class="card-header"><strong>Shipping Address</strong></div>
                    <div class="card-body" style="height: calc(100% - 56px); overflow-y: auto;">
                        <p><strong>Name:</strong> {{ $orderData['shipping_address']['name'] ?? 'N/A' }}</p>
                        <p><strong>Address1:</strong> {{ $orderData['shipping_address']['address1'] ?? '' }}</p>
                        <p><strong>City:</strong> {{ $orderData['shipping_address']['city'] ?? '' }}</p>
                        <p><strong>Province:</strong> {{ $orderData['shipping_address']['province'] ?? '' }}</p>
                        <p><strong>Zip:</strong> {{ $orderData['shipping_address']['zip'] ?? '' }}</p>
                        <p><strong>Country:</strong> {{ $orderData['shipping_address']['country'] ?? '' }}</p>
                        <p><strong>Phone:</strong> {{ $orderData['shipping_address']['phone'] ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>

            {{-- @php
                $hasData = collect($orderMetafields)->filter(fn($v) => !empty($v))->isNotEmpty();
            @endphp

            <div class="col-md-6">
                <div class="card mb-4" style="height: 400px;">
                    <div class="card-header"><strong>Order Metafields</strong></div>
                    <div class="card-body" style="height: calc(100% - 56px); overflow-y: auto;">
                        @if ($hasData)
                            @foreach ($orderMetafields as $key => $value)
                                @continue(empty($value))
                                <div class="mb-2">
                                    <strong>{{ ucwords(str_replace(['_', '-'], ' ', $key)) }}:</strong>
                                    @if (is_bool($value))
                                        {{ $value ? 'Yes' : 'No' }}
                                    @elseif (Str::startsWith($value, 'gid://shopify/MediaImage/'))
                                        <p><em>(Image GID: {{ $value }})</em></p>
                                    @elseif (filter_var($value, FILTER_VALIDATE_URL))
                                        <a href="{{ $value }}" target="_blank" rel="noopener noreferrer">Click Here
                                            <i class="fa-solid fa-up-right-from-square"></i></a>
                                    @else
                                        {{ $value }}
                                    @endif
                                </div>
                            @endforeach
                        @else
                            <p class="text-muted">No data found.</p>
                        @endif
                    </div>
                </div>
            </div> --}}
             <div class="col-md-6">
                <div class="card" style="max-height: 400px; overflow-y: auto;">
                    <div class="card-header">
                       <strong> Order Timeline</strong>
                    </div>
                    <div class="card-body">
                        @if ($auditDetails['logs']->isEmpty())
                            <p>No audit logs found for this order.</p>
                        @else
                            @foreach ($auditDetails['logs'] as $log)
                                <div class="mb-3 border-bottom pb-2">
                                    <div><strong>User:</strong> {{ $log->user_name }} ({{ $log->role_name }})</div>
                                    <div><strong>Action:</strong> {{ ucfirst($log->action) }}</div>
                                    <div><strong>Details:</strong> {{ $log->details }}</div>
                                    <div><strong>Date:</strong>
                                        {{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y h:i A') }}</div>
                                </div>
                            @endforeach
                        @endif

                        @if ($auditDetails['prescribed_pdf'])
                            <div class="mt-3">
                                <strong>Prescribed PDF:</strong>
                                <a href="{{ $auditDetails['prescribed_pdf'] }}" target="_blank"
                                    class="btn btn-sm btn-outline-primary">
                                    🔗 View PDF
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

        </div>


        <div class="card mb-4">
            <div class="card-header"><strong>Customer Information</strong></div>
            <div class="card-body">
                <p><strong>Name:</strong> {{ $orderData['customer']['first_name'] ?? '' }}
                    {{ $orderData['customer']['last_name'] ?? '' }}</p>
                <p><strong>Email:</strong> {{ $orderData['customer']['email'] ?? ($orderData['email'] ?? 'N/A') }}</p>
                <p><strong>Phone:</strong> {{ $orderData['customer']['phone'] ?? 'N/A' }}</p>
                <p><strong>Note:</strong> {{ $orderData['customer']['note'] ?? 'N/A' }}</p>

                <hr>

                <p><strong>Default Address:</strong><br>
                    {{ $orderData['customer']['default_address']['address1'] ?? '' }},
                    {{ $orderData['customer']['default_address']['city'] ?? '' }},
                    {{ $orderData['customer']['default_address']['province'] ?? '' }},
                    {{ $orderData['customer']['default_address']['zip'] ?? '' }},
                    {{ $orderData['customer']['default_address']['country'] ?? '' }}
                </p>
                <p><strong>Phone (Address):</strong> {{ $orderData['customer']['default_address']['phone'] ?? 'N/A' }}</p>

                <hr>

                <p><strong>Email Marketing Consent:</strong>
                    {{ ucfirst($orderData['customer']['email_marketing_consent']['state'] ?? 'N/A') }}
                    (Opt-in level: {{ $orderData['customer']['email_marketing_consent']['opt_in_level'] ?? 'N/A' }})
                </p>

                <p><strong>SMS Marketing Consent:</strong>
                    {{ ucfirst($orderData['customer']['sms_marketing_consent']['state'] ?? 'N/A') }}
                    (Opt-in level: {{ $orderData['customer']['sms_marketing_consent']['opt_in_level'] ?? 'N/A' }})
                </p>

                <p><strong>Tags:</strong> {{ $orderData['customer']['tags'] ?? 'N/A' }}</p>
            </div>
        </div>




        <div class="card mt-4">
            <div class="card-header"><strong>Consultation Details</strong></div>
            <div class="card-body">

                @foreach ($orderData['line_items'] as $item)
                    @php
                        $quizAnswers =
                            collect($item['properties'] ?? [])->firstWhere('name', '_quiz_kit_answers')['value'] ??
                            null;
                    @endphp

                    @if ($quizAnswers)
                        @php
                            // Step 1: Split entries by semicolon
                            $qaPairs = preg_split('/;\s*/', $quizAnswers);

                            // Step 2: Clean and split each into Q/A
                            $qaList = [];
                            foreach ($qaPairs as $qa) {
                                // Only split on the first colon
                                $parts = explode(':', $qa, 2);
                                if (count($parts) === 2) {
                                    $question = trim($parts[0]);
                                    $answer = trim($parts[1]);
                                    if ($question && $answer) {
                                        $qaList[] = ['question' => $question, 'answer' => $answer];
                                    }
                                }
                            }
                        @endphp

                        @foreach ($qaList as $qa)
                            <div class="row mb-1">
                                <div class="col-md-6"><strong>{{ $qa['question'] }}</strong></div>
                                <div class="col-md-6">{{ $qa['answer'] }}</div>
                            </div>
                        @endforeach
                    @else
                        <p>No consultation data found.</p>
                    @endif
                @endforeach

            </div>
        </div>



    </div>



    <!-- Approve Modal -->
    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('orders.prescribe', $order->order_number) }}">
                @csrf
                <input type="hidden" name="decision_status" value="approved">

                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Approve Prescription</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Clinical Reasoning</label>
                            <textarea name="clinical_reasoning" class="form-control" required>{{ old('clinical_reasoning') }}</textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-success" id="submit-approval">Submit Approval</button>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('orders.prescribe', $order->order_number) }}">
                @csrf
                <input type="hidden" name="decision_status" value="rejected">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Reject Prescription</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <textarea name="rejection_reason" class="form-control" placeholder="Rejection reason" required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-danger" id="submit-reject">Submit</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- On Hold Modal -->
    <div class="modal fade" id="onHoldModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('orders.prescribe', $order->order_number) }}">
                @csrf
                <input type="hidden" name="decision_status" value="on_hold">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Put On Hold</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <textarea name="on_hold_reason" class="form-control" placeholder="Reason for putting on hold" required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-warning" id="submit-hold">Submit</button>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <div class="modal fade" id="releaseHoldModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('orders.prescribe', $order->order_number) }}">
                @csrf
                <input type="hidden" name="decision_status" value="release_hold">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Release Hold</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <textarea name="release_hold_reason" class="form-control" placeholder="Reason for releasing hold" required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-warning" id="submit-release">Submit</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

@endsection
@section('custom_js_scripts')
    <script>
        const loader = document.getElementById('loaderOverlay');

        ['submit-approval', 'submit-reject', 'submit-hold', 'submit-release'].forEach(id => {
            const button = document.getElementById(id);
            if (button) {
                button.addEventListener('click', function() {
                    loader.style.display = 'flex';
                });
            }
        });
    </script>
@endsection
