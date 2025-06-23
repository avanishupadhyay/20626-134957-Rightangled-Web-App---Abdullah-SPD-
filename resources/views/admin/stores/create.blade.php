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

                                    <div class="col-md-6 form-group">
                                        <label for="AddressId">Address ID</label>
                                        <input type="text" name="AddressId" id="AddressId" class="form-control"
                                            value="{{ old('AddressId') }}" maxlength="100">
                                        @error('AddressId')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="ShipperReference">Shipper Reference</label>
                                        <input type="text" name="ShipperReference" id="ShipperReference"
                                            class="form-control" value="{{ old('ShipperReference') }}" maxlength="100">
                                        @error('ShipperReference')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="ShipperReference2">Shipper Reference 2</label>
                                        <input type="text" name="ShipperReference2" id="ShipperReference2"
                                            class="form-control" value="{{ old('ShipperReference2') }}" maxlength="100">
                                        @error('ShipperReference2')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="ShipperDepartment">Shipper Department</label>
                                        <input type="text" name="ShipperDepartment" id="ShipperDepartment"
                                            class="form-control" value="{{ old('ShipperDepartment') }}" maxlength="100">
                                        @error('ShipperDepartment')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="ContactName">Contact Name</label>
                                        <input type="text" name="ContactName" id="ContactName" class="form-control"
                                            value="{{ old('ContactName') }}" maxlength="100">
                                        @error('ContactName')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="AddressLine1">Address Line 1</label>
                                        <input type="text" name="AddressLine1" id="AddressLine1" class="form-control"
                                            value="{{ old('AddressLine1') }}" maxlength="100">
                                        @error('AddressLine1')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="Town">Town</label>
                                        <input type="text" name="Town" id="Town" class="form-control"
                                            value="{{ old('Town') }}" maxlength="100">
                                        @error('Town')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="County">County</label>
                                        <input type="text" name="County" id="County" class="form-control"
                                            value="{{ old('County') }}" maxlength="100">
                                        @error('County')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="CountryCode">Country Code</label>
                                        <input type="text" name="CountryCode" id="CountryCode" class="form-control"
                                            value="{{ old('CountryCode') }}" maxlength="100">
                                        @error('CountryCode')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="Postcode">Postcode</label>
                                        <input type="text" name="Postcode" id="Postcode" class="form-control"
                                            value="{{ old('Postcode') }}" maxlength="20">
                                        @error('Postcode')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="PhoneNumber">Phone Number</label>
                                        <input type="text" name="PhoneNumber" id="PhoneNumber" class="form-control"
                                            value="{{ old('PhoneNumber') }}" maxlength="20">
                                        @error('PhoneNumber')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="EmailAddress">Email Address</label>
                                        <input type="email" name="EmailAddress" id="EmailAddress" class="form-control"
                                            value="{{ old('EmailAddress') }}" maxlength="100">
                                        @error('EmailAddress')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 form-group">
                                        <label for="VatNumber">VAT Number</label>
                                        <input type="text" name="VatNumber" id="VatNumber" class="form-control"
                                            value="{{ old('VatNumber') }}" maxlength="30">
                                        @error('VatNumber')
                                            <p class="text-danger">{{ $message }}</p>
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
                                        <input type="text" name="app_admin_access_token" id="title"
                                            class="form-control" maxlength="255"
                                            value="{{ old('app_admin_access_token') }}">
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
                                            <label class="custom-control-label"
                                                for="StoreEditable">Enabled/Disabled</label>
                                        </div>
                                    </div>
                                
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

    </div>
@endsection
