@extends('admin.layouts.app')

@section('content')
    <div class="row page-titles mx-0 mb-3">
        <div class="col-sm-6 p-0">
            <div class="welcome-text">
                <h4>Dispensed Batches</h4>
            </div>
        </div>
        <div class="col-sm-6 p-0 justify-content-sm-end mt-2 mt-sm-0 d-flex">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('dispenser_orders.index') }}">Dispensers</a></li>
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
                    <form method="GET" action="{{ route('dispenser.batches.list') }}" class="row g-2 align-items-end">
                        {{-- Search --}}
                        <div class="col-md-3">
                            <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                                placeholder="Search by Batch Number">
                        </div>

                        {{-- Filter button --}}
                        <div class="col-md-1 d-grid">
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </div>

                        {{-- Clear button --}}
                        <div class="col-md-1 d-grid">
                            <a href="{{ route('dispenser.batches.list') }}" class="btn btn-secondary">Clear</a>
                        </div>

                    </form>

                </div>

            </div>
        </div>

    </div>
    <div class="card">
        <div class="card-header">Dispensed Batches</div>
        <div class="card-body">
            @if ($batches->isEmpty())
                <p>No dispensed batches found.</p>
            @else
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Batch Number</th>
                            <th>Dispensed By</th>
                            <th>Dispensed At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($batches as $batch)
                            <tr>
                                <td>{{ $batch->batch_number }}</td>
                                <td>{{ $batch->user->name ?? 'N/A' }}</td>
                                <td>{{ \Carbon\Carbon::parse($batch->created_at)->format('d/m/Y H:i') }}</td>
                                <td>
                                    {{-- <a href="{{ route('dispenser.batches.download', $batch->id) }}"
                                        class="btn btn-sm btn-success"> <i class="fa fa-download"></i></a> --}}
                                    {{-- <button class="btn btn-sm btn-primary"
                                        onclick="openAndPrintPDF('{{ asset('storage/' . $batch->pdf_path) }}')">
                                        <i class="fa fa-print"></i>
                                    </button> --}}
                                    @php
                                        $path = $batch->pdf_path ?? $batch->shipment_pdf_path;
                                    @endphp
                                    <button class="btn btn-sm btn-primary"
                                        onclick="openAndPrintPDF('{{ $batch->id }}', '{{ asset('storage/' . $path) }}')">
                                        <i class="fa fa-print"></i>
                                    </button>

                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="mt-1">
                    {!! $batches->appends(request()->query())->links('pagination::bootstrap-5') !!}
                </div>
            @endif
        </div>

    </div>
    
    {{-- @php
        $path = $batch->pdf_path ?? $batch->shipment_pdf_path;
    @endphp

    @if (session('batch_id'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const link = document.createElement('a');
                link.href = '{{ asset('storage/' . $path) }}';
                link.setAttribute('download', '');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        </script>

        @php
            session()->forget('batch_id');
        @endphp
    @endif --}}


@endsection
{{-- <script>
    function openAndPrintPDF(pdfUrl) {
        const printWindow = window.open(pdfUrl, '_blank');

        const checkLoaded = setInterval(() => {
            if (printWindow.document.readyState === 'complete') {
                // printWindow.focus();
                // printWindow.print();
                // clearInterval(checkLoaded);
            }
        }, 500);
    }
</script> --}}

<script>
    function openAndPrintPDF(batchId, pdfUrl) {
        fetch(`/admin/dispenser/batches/${batchId}/increment-reprint`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({})
            })
            .then(response => response.json().then(data => ({
                status: response.status,
                body: data
            })))
            .then(({
                status,
                body
            }) => {
                if (status === 200 && body.success) {
                    toastr.success(body.message);
                    setTimeout(() => {
                        window.open(pdfUrl, '_blank');
                    }, 1000);
                } else {
                    toastr.error(body.message || 'An error occurred.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                toastr.error('Something went wrong.');
            });
    }
</script>
