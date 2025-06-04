@extends('admin.layouts.app')
@role('Admin')
@section('content')
    <div class="row page-titles mx-0 mb-3">
        <div class="col-sm-6 p-0">
            <div class="welcome-text">
                <h4>User</h4>
            </div>
        </div>
        <div class="col-sm-6 p-0 justify-content-sm-end mt-2 mt-sm-0 d-flex">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('users.index') }}">User</a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </div>
    </div>


    @if (count($errors) > 0)
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif



<div class="row">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Edit</h4>
                </div>
                <div class="card-body">
                    <!-- Nav tabs -->
                    <div class="default-tab">
                        
    <form method="POST" action="{{ route('users.update', $user->id) }}">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-xs-12 col-sm-12 col-md-6">
                <div class="form-group">
                    <strong>Name:</strong>
                    <input type="text" name="name" placeholder="Name" class="form-control"
                        value="{{ old('name', $user->name) }}">
                </div>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-6">
                <div class="form-group">
                    <strong>Email:</strong>
                    <input type="email" name="email" placeholder="Email" class="form-control"
                        value="{{ old('email', $user->email) }}">
                </div>
            </div>
              <div class="col-xs-12 col-sm-12 col-md-6">
                <div class="form-group">
                    <strong>Role:</strong>
                    <select name="roles[]" class="form-control">
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}"
                                {{ in_array($value, old('roles', array_keys($userRole))) ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-6">
                <div class="form-group">
                    <strong>Password:</strong>
                    <input type="password" name="password" placeholder="Password" class="form-control">
                    <small class="text-muted">Leave blank if you don't want to change the password</small>
                </div>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-6">
                <div class="form-group">
                    <strong>Confirm Password:</strong>
                    <input type="password" name="confirm-password" placeholder="Confirm Password" class="form-control">
                </div>
            </div>
       
            <div class="col-xs-12 col-sm-12 col-md-6">
                <div class="form-group">
                    <strong>Status:</strong>
                    <select name="status" class="form-control">
                        <option value="1" {{ old('status', $user->status) == '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ old('status', $user->status) == '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
            </div>
          
            <div class="col-xs-12 col-sm-12 col-md-12 text-center">
                <button type="submit" class="btn btn-primary btn-md mt-2 mb-3">
                   Update
                </button>
            </div>
        </div>
    </form>
                    </div>
                </div>
            </div>
        </div>
</div>


@endsection
@endrole