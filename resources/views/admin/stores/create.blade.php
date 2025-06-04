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
                    <li class="breadcrumb-item"><a href="{{ route('admin.stores.index') }}">Store</a></li>
                    <li class="breadcrumb-item active">Add</li>
                </ol>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Add Store</h4>
                    </div>
                    <div class="card-body">
                        <!-- Nav tabs -->
                        <div class="default-tab">
                            <form action="{{ route('admin.stores.store') }}" method="POST" enctype="multipart/form-data">
                                @csrf


                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label for="StoreName">Name</label>
                                        <input type="text" name="name" id="Name" class="form-control"
                                            value="{{ old('name') }}" maxlength="100">
                                        @error('name')
                                            <p class="text-danger">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>
                                    <div class="col-md-12 form-group">
                                        <label for="Description">Description</label>
                                        <textarea name="description" id="Description" class="form-control" cols="30" rows="6">{{ old('description') }}</textarea>
                                        @error('description')
                                            <p class="text-danger">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="title">Store URL</label>
                                        <input type="text" name="domain" id="title" class="form-control"
                                            maxlength="255" value="{{ old('domain') }}">
                                        @error('domain')
                                            <p class="text-danger">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="StoreImage">Image</label>
                                        <div class="form-group">
                                            @if (!empty($store->image))
                                                <img src="{{ asset('storage/stores/' . $store->image) }}" alt="Store"
                                                    class="configurationPrefixImg img-for-onchange">
                                            @endif

                                            <div class="form-file d-inline-block">
                                                <input type="file" accept=".png, .jpg, .jpeg" name="image"
                                                    class="form-file-input img-input-onchange ps-2 form-control">
                                            </div>
                                            @error('image')
                                                <p class="text-danger">
                                                    {{ $message }}
                                                </p>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label for="title">App Client Id</label>
                                        <input type="text" name="app_client_id" id="title" class="form-control"
                                            maxlength="255" value="{{ old('app_client_id') }}">
                                        @error('app_client_id')
                                            <p class="text-danger">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>
									 <div class="col-md-6 form-group">
                                        <label for="title">App Secret Key</label>
                                        <input type="text" name="app_secret_key" id="title" class="form-control"
                                            maxlength="255" value="{{ old('app_secret_key') }}">
                                        @error('app_secret_key')
                                            <p class="text-danger">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>
									 <div class="col-md-6 form-group">
                                        <label for="title">App Admin Access Token</label>
                                        <input type="text" name="app_admin_access_token" id="title" class="form-control"
                                            maxlength="255" value="{{ old('app_admin_access_token') }}">
                                        @error('app_admin_access_token')
                                            <p class="text-danger">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>
									

                                    <div class="col-md-6 form-group mt-4">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" name="status" id="StoreEditable"
                                                class="custom-control-input  form-check-input" checked="checked">
                                            <label class="custom-control-label" for="StoreEditable">Enabled/Disabled</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12 text-right">
                                        <button type="submit" class="btn btn-primary">Save</button>
                                    </div>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
