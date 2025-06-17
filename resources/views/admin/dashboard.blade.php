@extends('admin.layouts.app')

@section('content')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- In your <head> or layout -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css" />
    <script src="https://cdn.jsdelivr.net/npm/litepicker/dist/bundle.js"></script>
    <div class="container-fluid">
        <div class="row page-titles mx-0 mb-3">
            <div class="col-sm-6 p-0">
                <div class="welcome-text">
                    <h4>Dashboard</h4>
                </div>
            </div>
        </div>
        <hr>
        <div class="row">

            @php
                // $startDate = request('start_date') ?? \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d');
                // $endDate = request('end_date') ?? \Carbon\Carbon::now()->format('Y-m-d');
                $startDate = request('start_date');
                $endDate = request('end_date');
                $selectedPreset = request('preset');
            @endphp

            <div class="d-flex align-items-center gap-2">


                <form id="date-filter-form" method="GET" action="{{ route('admin.dashboard') }}"
                    style="display: flex; gap: 10px; align-items: center;">

                    <select id="preset-select" name="preset" class="form-select"
                        style="width: 200px; border: 1px solid #ccc; border-radius: 4px;">
                        <option value="">-- Select Preset --</option>
                        @foreach (['Today', 'Yesterday', 'Last 7 days', 'Last 30 days', 'Last 6 months', 'Last 12 months', 'Month-to-date (MTD)', 'Year-to-date (YTD)', 'Lifetime'] as $preset)
                            <option value="{{ $preset }}" {{ $selectedPreset == $preset ? 'selected' : '' }}>
                                {{ $preset }}
                            </option>
                        @endforeach
                    </select>

                    <input type="text" id="date-range" class="form-control" placeholder="Select Date" readonly />
                    <input type="hidden" name="start_date" id="start-date" value="{{ $startDate }}" />
                    <input type="hidden" name="end_date" id="end-date" value="{{ $endDate }}" />
                    <button type="submit" class="btn btn-primary">
                        Apply
                    </button>
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">clear</a>
                </form>
            </div>
        </div><br>
        <div class="row">
            <div class="col-sm-12">
                <div class="row">
                    <div class="col-sm-6 col-md-6 mb-3">
                        {{-- bg-light-info --}}
                        <div class="card prod-p-card background-pattern">
                            <div class="card-body">
                                <div class="row align-items-center m-b-0">
                                    @if (request('start_date') && empty(request('preset')))
                                        <span>Total Orders</span>
                                    @else
                                        <span>Total Orders Of {{ $selectedPreset ?? 'Current Month' }}</span>
                                    @endif
                                    <strong>{{ $current_month_order }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-6 mb-3">
                        {{-- bg-light-success --}}
                        <div class="card prod-p-card background-pattern">
                            <div class="card-body">
                                <div class="row align-items-center m-b-0">
                                    @if (request('start_date') && empty(request('preset')))
                                        <span>Total Approved Orders</span>
                                    @else
                                        <span>Total Approved Orders Of {{ $selectedPreset ?? 'Current Month' }}</span>
                                    @endif
                                    <strong>{{ $approved_count }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- <div class="col-sm-4 col-md-4 mb-3">
                        <div class="card prod-p-card background-pattern">
                            <div class="card-body">
                                <div class="row align-items-center m-b-0">
                                    <span>Current Inventory Value</span>
                                    <strong>$580</strong>
                                </div>
                            </div>
                        </div>
                    </div> --}}
                </div>
            </div>
        </div>
        <div class="card p-4">
            {{-- <div class="row"> --}}
            {{-- <div class="col-md-8">
                    <div class="row"> --}}
            {{-- <h5>Trends</h5> --}}
            <canvas id="lineChart" width="800" height="400"></canvas>
            {{-- </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4">
                    Inventory By Category
                </div>
            </div> --}}
        </div><br>
    </div>
@endsection
@section('custom_js_scripts')
    <script>
        const chartData = @json($data); // ✅ Correct way

        const labels = chartData.map(item => item.month);
        const totalData = chartData.map(item => item.total);
        const approvedData = chartData.map(item => item.approved);

        const ctx = document.getElementById('lineChart').getContext('2d');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                        label: 'Total Orders',
                        data: totalData,
                        borderColor: 'blue',
                        tension: 0.3
                    },
                    {
                        label: 'Approved Orders',
                        data: approvedData,
                        borderColor: 'green',
                        tension: 0.3
                    },
                    // {
                    //     label: 'On Hold Orders',
                    //     data: onHoldData,
                    //     borderColor: 'yellow',
                    //     tension: 0.3
                    // },
                    // {
                    //     label: 'Rejected Orders',
                    //     data: RejectedData,
                    //     borderColor: 'red',
                    //     tension: 0.3
                    // },
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
            // options: {
            //     responsive: true,
            //     scales: {
            //         y: {
            //             beginAtZero: true,
            //             min: 0,
            //             max: 1000,
            //             ticks: {
            //                 stepSize: 50
            //             }
            //         }
            //     }
            // }
        });
    </script>
    <!-- Include Litepicker + Dayjs -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css" />
    <script src="https://cdn.jsdelivr.net/npm/litepicker/dist/bundle.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dayjs@1/dayjs.min.js"></script>

    <script>
        // Set initial dates from Blade variables
        const initialStart = "{{ $startDate }}";
        const initialEnd = "{{ $endDate }}";

        const picker = new Litepicker({
            element: document.getElementById('date-range'),
            singleMode: false,
            format: 'YYYY-MM-DD',
            autoApply: true,
            setup: (picker) => {
                picker.on('selected', (start, end) => {
                    document.getElementById('start-date').value = start.format('YYYY-MM-DD');
                    document.getElementById('end-date').value = end.format('YYYY-MM-DD');
                });
            }
        });

        // Set the default selected date on page load
        // picker.setDateRange(initialStart, initialEnd);
        if (initialStart && initialEnd) {
            picker.setDateRange(initialStart, initialEnd);
        }
        document.getElementById('preset-select').addEventListener('change', function() {
            const selected = this.value;
            if (!selected) return;

            const now = dayjs();
            let start, end;

            switch (selected) {
                case 'Today':
                    start = end = now;
                    break;
                case 'Yesterday':
                    start = end = now.subtract(1, 'day');
                    break;
                case 'Last 7 days':
                    start = now.subtract(6, 'day');
                    end = now;
                    break;
                case 'Last 30 days':
                    start = now.subtract(29, 'day');
                    end = now;
                    break;
                case 'Last 6 months':
                    start = now.subtract(6, 'month').startOf('month');
                    end = now;
                    break;
                case 'Last 12 months':
                    start = now.subtract(12, 'month').startOf('month');
                    end = now;
                    break;
                case 'Month-to-date (MTD)':
                    start = now.startOf('month');
                    end = now;
                    break;
                case 'Year-to-date (YTD)':
                    start = now.startOf('year');
                    end = now;
                    break;
                case 'Lifetime':
                    start = dayjs('2000-01-01');
                    end = now;
                    break;
            }

            picker.setDateRange(start.toDate(), end.toDate());
            document.getElementById('start-date').value = start.format('YYYY-MM-DD');
            document.getElementById('end-date').value = end.format('YYYY-MM-DD');
        });
    </script>
@endsection
