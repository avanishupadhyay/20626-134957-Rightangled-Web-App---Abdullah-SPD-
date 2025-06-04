@extends('admin.layouts.app')
<!-- Select2 CSS -->
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
                    <li class="breadcrumb-item active">Add</li>
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
                        <h4 class="card-title">Add User</h4>
                    </div>
                    <div class="card-body">
                        <!-- Nav tabs -->
                        <div class="default-tab">
                            <form method="POST" action="{{ route('users.store') }}">
                                @csrf
                                <div class="row">
                                    <div class="col-xs-12 col-sm-12 col-md-6">
                                        <div class="form-group">
                                            <strong>Name:</strong>
                                            <input type="text" name="name" value="{{ old('name') }}" placeholder="Name"
                                                class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-xs-12 col-sm-12 col-md-6">
                                        <div class="form-group">
                                            <strong>Email:</strong>
                                            <input type="email" name="email" value="{{ old('email') }}" placeholder="Email"
                                                class="form-control">
                                        </div>
                                    </div>
                                      <div class="col-xs-12 col-sm-12 col-md-6">
                                        <div class="form-group mb-3">
                                            <strong>Select Roles</strong>
                                            <select name="roles[]" id="roles" class="form-control">
                                                @foreach ($roles as $value => $label)
                                                    <option value="{{ $value }}"
                                                        {{ in_array($value, old('roles', [])) ? 'selected' : '' }}>
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
                                        </div>
                                    </div>
                                    <div class="col-xs-12 col-sm-12 col-md-6">
                                        <div class="form-group">
                                            <strong>Confirm Password:</strong>
                                            <input type="password" name="confirm-password" placeholder="Confirm Password"
                                                class="form-control">
                                        </div>
                                    </div>
                                  
                                    <div class="col-xs-12 col-sm-12 col-md-12 text-center">
                                        <button type="submit" class="btn btn-primary btn-md mt-2 mb-3"><i class=""></i>
                                            Submit</button>
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
