@extends('admin.layouts.app')

@section('content')
    <div class="container">
        <h2 class="mb-4">Shipment Batches</h2>

        <div class="col-xl-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Search</h4>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('batches.index') }}" class="row g-2 align-items-end">
                        {{-- Search --}}
                        <div class="col-md-4">
                            <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                                placeholder="Search by batch_number or order number">
                        </div>
                        <div class="col-md-1 d-grid">
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </div>

                        {{-- Clear button --}}
                        <div class="col-md-1 d-grid ms-1">
                            <a href="{{ route('batches.index') }}" class="btn btn-secondary">Clear</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Batch Number</th>
                        <th>Total Orders Dispensed</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($grouped as $batchId => $dispensesGroup)
                        @php
                            $batch = $dispensesGroup->first()->batch;
                            $orderCount = $dispensesGroup->count();
                        @endphp
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $batch->batch_number ?? 'N/A' }}</td>
                            <td>{{ $orderCount }}</td>
                            <td>{{ $batch?->created_at?->format('d M Y, h:i A') ?? '   N/A' }}</td>
                            <td>
                                <a href="{{ route('admin.batches.download', $batch->id) }}"
                                        class="btn btn-sm btn-success"> <i class="fa fa-download"></i></a>
                                </a> 
                                <a href="javascript:void(0);" class="btn btn-primary btn-sm"
                                    onclick="confirmAndPrint({{ $batchId }})">
                                    <i class="fa fa-print"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">No batches found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-1">
                {!! $dispenses->links('pagination::bootstrap-5') !!}
            </div>
        </div>
    </div>
@endsection
{{-- <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> --}}

<script>
    function confirmAndPrint(batchId) {
        fetch(`/admin/batches/${batchId}/check-reprint`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({})
            })
            .then(response => response.json())
            .then(data => {
                if (data.alreadyPrinted) {
                    Swal.fire({
                        title: 'Already Printed!',
                        text: "This batch has already been printed. Do you want to print again?",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, print again'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href =
                            `/admin/batches/${batchId}/download`; // your existing route
                        }
                    });
                } else {
                    // First time printing
                    window.location.href = `/admin/batches/${batchId}/download`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'Something went wrong.', 'error');
            });
    }
</script>
