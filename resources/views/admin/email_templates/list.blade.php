@extends('admin.layouts.app')

@section('content')
    <style>
        .table-responsive {
            overflow-x: auto;
            width: 100%;
        }
    </style>
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center"  style="width: -webkit-fill-available;">
                <h4>Email Templates</h4>
                <a href="{{ route('admin.email-templates.create') }}" class="btn btn-primary">Add Template</a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Sr. No.</th>
                            <th>Key</th>
                            <th>Subject</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $i=1; @endphp
                        @foreach ($template as $key => $value)
                            <tr>
                                <td>{{ $i }}</td>
                                <td>{{ $value['identifier'] }}</td>
                                <td>{{ $value['subject'] }}</td>
                                <td>
                                    <div class="d-flex gap-2 justify-content-center">
                                        {{-- <a href=""><i class="fa fa-eye"></i></a> --}}
                                        <a href="{{ route('admin.email-templates.edit', ['key' => $value['identifier']]) }}"><i class="fa fa-edit"></i></a>
                                    </div>
                                </td>
                            </tr>
                            @php $i++; @endphp
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
