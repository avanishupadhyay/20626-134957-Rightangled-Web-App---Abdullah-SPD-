{{-- Extends layout --}}
@extends('admin.layouts.app')

{{-- Content --}}
@section('content')
    <div class="container-fluid">
        <div class="row page-titles mx-0 mb-3">
            <div class="col-sm-6 p-0">
                <div class="welcome-text">
                    <h4>Store</h4>
                </div>
            </div>
            <div class="col-sm-6 p-0 justify-content-sm-end mt-2 mt-sm-0 d-flex">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Store List</li>
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
                        <form action="{{ route('admin.stores.index') }}" method="get">
                            @csrf
                            <div class="row">
                                <div class="mb-3 col-md-3">
                                    <input type="search" name="name" class="form-control" placeholder="Name"
                                        value="{{ old('name', request()->input('name')) }}">
                                </div>
                                <div class="mb-3 col-md-3">
                                    <input type="search" name="domain" class="form-control" placeholder="Domain"
                                        value="{{ old('domain', request()->input('domain')) }}">
                                </div>
                                <div class="mb-3 col-md-3">
                                    <select name="status" class="form-control">
                                        <option value="">Choose Status</option>
                                        <option value="1" {{ request()->input('status') == 1 ? 'selected' : '' }}>
                                            Enabled</option>
                                        <option value="0"
                                            {{ request()->input('status') == 0 && request()->input('status') != '' ? 'selected' : '' }}>
                                            Disabled</option>
                                    </select>
                                </div>
                                <div class="mb-3 col-md-3 text-end">
                                    <input type="submit" name="search" value="Search" class="btn btn-primary me-2">
                                    <a href="{{ route('admin.stores.index') }}" class="btn btn-danger">Reset</a>
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
                        <h4 class="card-title">Store</h4>
                        <a href="{{ route('admin.stores.create') }}" class="btn btn-primary">Add Store</a>
                    </div>
                    <div class="pe-4 ps-4 pt-2 pb-2">
                        <div class="table-responsive">
                            <table class="table table-responsive-lg mb-0">
                                <thead>
                                    <tr>
                                        <th> <strong> S.No </strong> </th>
                                        <th> <strong> Name </strong> </th>
                                        <th> <strong> Domain </strong> </th>
                                        <th> <strong> Status </strong> </th>
                                        <th class="text-center" width="150px"> <strong> Actions </strong> </th>
                                    </tr>
                                </thead>
                                <tbody>

                                    @php
                                        $sNo =
                                            ($stores->currentPage() - 1) * $stores->perPage() +
                                            1; /* Increasing Serial Number */
                                    @endphp
                                    @forelse ($stores as $store)
                                        <tr>
                                            <td> {{ $sNo++ }} </td>
                                            <td> {{ $store->name }} </td>
                                            <td> {!! $store->domain !!} </td>
                                            <td> {{ $store->status ? 'Enabled' : 'Disabled' }} </td>
                                            <td>
                                                <a href="{{ route('admin.stores.edit', $store) }}"
                                                    class="btn btn-primary shadow btn-xs sharp mr-1"><i
                                                        class="fas fa-pencil-alt"></i></a>

                                                <form action="{{ route('admin.stores.destroy', $store->id) }}"
                                                    method="POST" style="display: inline;" class="delete-confirm">
                                                    @csrf
                                                    @method('DELETE')

                                                    <button type="button" class="btn btn-danger shadow btn-xs sharp"
                                                        data-title="Are You Sure?"
                                                        data-text="You won't be able to restore it!"
                                                        data-confirm-button="Yes, delete it!" data-cancel-button="Cancel"><i
                                                            class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>


                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center">
                                                <p>Records Not Found</p>
                                            </td>
                                        </tr>
                                    @endforelse

                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if ($stores->hasPages())
                        <div class="card-footer">
                            {{ $stores->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            attachDeleteConfirm('.delete-confirm', {});
            console.log("hello");
        });
    </script>
@endsection
