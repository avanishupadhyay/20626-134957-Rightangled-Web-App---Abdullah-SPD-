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
                    <li class="breadcrumb-item active">Edit</li>
                </ol>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Edit</h4>
                    </div>
                    <div class="card-body">
                        <!-- Nav tabs -->
                        <div class="default-tab">

                            <form action="{{ route('admin.stores.update', $store->id) }}" method="POST"
                                enctype="multipart/form-data">
                                @csrf
                                @method('PUT')
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label for="StoreName">Name</label>
                                        <input type="text" name="name" id="Name" class="form-control"
                                            value="{{ old('name', $store->name) }}" maxlength="64">
                                        @error('name')
                                            <p class="text-danger">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="AddressId">Address ID</label>
                                        <input type="text" name="AddressId" id="AddressId" class="form-control"
                                            value="{{ old('AddressId', $store->AddressId) }}" maxlength="100">
                                        @error('AddressId')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="ShipperReference">Shipper Reference</label>
                                        <input type="text" name="ShipperReference" id="ShipperReference"
                                            class="form-control" value="{{ old('ShipperReference', $store->ShipperReference) }}" maxlength="100">
                                        @error('ShipperReference')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="ShipperReference2">Shipper Reference 2</label>
                                        <input type="text" name="ShipperReference2" id="ShipperReference2"
                                            class="form-control" value="{{ old('ShipperReference2', $store->ShipperReference2) }}" maxlength="100">
                                        @error('ShipperReference2')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="ShipperDepartment">Shipper Department</label>
                                        <input type="text" name="ShipperDepartment" id="ShipperDepartment"
                                            class="form-control" value="{{ old('ShipperDepartment', $store->ShipperDepartment) }}" maxlength="100">
                                        @error('ShipperDepartment')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="ContactName">Contact Name</label>
                                        <input type="text" name="ContactName" id="ContactName" class="form-control"
                                            value="{{ old('ContactName', $store->ContactName) }}" maxlength="100">
                                        @error('ContactName')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="AddressLine1">Address Line 1</label>
                                        <input type="text" name="AddressLine1" id="AddressLine1" class="form-control"
                                            value="{{ old('AddressLine1', $store->AddressLine1) }}" maxlength="100">
                                        @error('AddressLine1')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="Town">Town</label>
                                        <input type="text" name="Town" id="Town" class="form-control"
                                            value="{{ old('Town', $store->Town) }}" maxlength="100">
                                        @error('Town')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="County">County</label>
                                        <input type="text" name="County" id="County" class="form-control"
                                            value="{{ old('County', $store->County) }}" maxlength="100">
                                        @error('County')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="CountryCode">Country Code</label>
                                        <input type="text" name="CountryCode" id="CountryCode" class="form-control"
                                            value="{{ old('CountryCode', $store->CountryCode) }}" maxlength="100">
                                        @error('CountryCode')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="Postcode">Postcode</label>
                                        <input type="text" name="Postcode" id="Postcode" class="form-control"
                                            value="{{ old('Postcode', $store->Postcode) }}" maxlength="20">
                                        @error('Postcode')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="PhoneNumber">Phone Number</label>
                                        <input type="text" name="PhoneNumber" id="PhoneNumber" class="form-control"
                                            value="{{ old('PhoneNumber', $store->PhoneNumber) }}" maxlength="20">
                                        @error('PhoneNumber')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="EmailAddress">Email Address</label>
                                        <input type="email" name="EmailAddress" id="EmailAddress" class="form-control"
                                            value="{{ old('EmailAddress', $store->EmailAddress) }}" maxlength="100">
                                        @error('EmailAddress')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="VatNumber">VAT Number</label>
                                        <input type="text" name="VatNumber" id="VatNumber" class="form-control"
                                            value="{{ old('VatNumber', $store->VatNumber) }}" maxlength="30">
                                        @error('VatNumber')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-12 form-group">
                                        <label for="Description">Description</label>
                                        <textarea name="description" id="Description" class="form-control" rows="4">{{ old('description', $store->description) }}</textarea>
                                        @error('description')
                                            <p class="text-danger">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="title">Store URL</label>
                                        <input type="text" name="domain" id="domain" class="form-control"
                                            value="{{ old('domain', $store->domain) }}">
                                        @error('domain')
                                            <p class="text-danger">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="StoreParams">Image</label>
                                        <div class="form-group">
                                            @if (!empty($store->image))
                                                <div class="mb-1">
                                                    <img src="{{ asset('storage/stores/' . $store->image) }}"
                                                        alt="Store Image" class="img-thumbnail rounded"
                                                        style="height: 60px; width: 60px; object-fit: cover;">
                                                </div>
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
                                        <input type="text" name="app_client_id" id="domain" class="form-control"
                                            value="{{ old('app_client_id', $store->app_client_id) }}">
                                        @error('app_client_id')
                                            <p class="text-danger">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label for="title">App Secret Key</label>
                                        <input type="text" name="app_secret_key" id="domain" class="form-control"
                                            value="{{ old('app_secret_key', $store->app_secret_key) }}">
                                        @error('app_secret_key')
                                            <p class="text-danger">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label for="title">App Admin Access Token</label>
                                        <input type="text" name="app_admin_access_token" id="domain"
                                            class="form-control"
                                            value="{{ old('app_admin_access_token', $store->app_admin_access_token) }}">
                                        @error('app_admin_access_token')
                                            <p class="text-danger">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 form-group mt-4">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" name="status" id="StoreStatus"
                                                class="custom-control-input  form-check-input"
                                                {{ $store->status ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="StoreStatus">Enabled/Disabled</label>
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
