@extends('admin.layouts.app')
@role('Admin')
    @section('content')
        <div class="row mb-3">
            <div class="col-md-9">
                <form method="GET" action="{{ route('users.index') }}" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                            placeholder="Filter by name or email">
                    </div>

                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ request('status') == '0' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <select name="role" class="form-select">
                            <option value="">All Roles</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role }}" {{ request('role') == $role ? 'selected' : '' }}>
                                    {{ $role }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-1 d-grid">
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>

                    <div class="col-md-1 d-grid ms-2">
                        <a href="{{ route('users.index') }}" class="btn btn-secondary">Clear</a>
                    </div>
                </form>
            </div>

            <div class="col-md-3 text-end">
                <a class="btn btn-success" href="{{ route('users.create') }}">
                    <i class="fa fa-plus"></i> Create New User
                </a>
            </div>
        </div>


        <table class="table table-bordered">
            <tr>
                <th>S.No</th>
                <th>Name</th>
                <th>Email</th>
                <th>Status</th>
                <th>Roles</th>
                <th width="280px">Action</th>
            </tr>
            @foreach ($data as $key => $user)
                <tr>
                    <td>{{ ++$i }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>
                        @if ($user->status == 1)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-danger">Inactive</span>
                        @endif
                    </td>

                    <td>
                        @if (!empty($user->getRoleNames()))
                            @foreach ($user->getRoleNames() as $v)
                                <label class="badge bg-info">{{ $v }}</label>
                            @endforeach
                        @endif
                    </td>
                    <td>

                        <a class="btn btn-primary btn-sm" href="{{ route('users.edit', $user->id) }}"><i
                                class="fa-solid fa-pen-to-square" style="width: 100px"></i> </a>

                        <form method="POST" action="{{ route('users.destroy', $user->id) }}" style="display:inline"
                            class="delete-form">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-danger btn-sm delete-confirm ms-2"
                                data-title="Are You Sure?" data-text="You won't be able to restore it!"
                                data-confirm-button="Yes, delete it!" data-cancel-button="Cancel"><i
                                    class="fa-solid fa-trash"></i></button>
                        </form>

                    </td>
                </tr>
            @endforeach
        </table>

        {!! $data->links('pagination::bootstrap-5') !!}
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                attachDeleteConfirm('.delete-confirm', {
                });
            });
        </script>
    @endsection
@endrole
