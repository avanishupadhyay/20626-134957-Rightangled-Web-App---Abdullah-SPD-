<form action="{{ route('admin.stores.set_store') }}" method="POST">
	@csrf
	<div class="modal-header">
		<h5 class="modal-title" id="ajaxModalLabel">Choose Store</h5>
		<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
	</div>
	<div class="modal-body">
		<div class="form-group">
			<label for="store_id"><strong>Store</strong></label>
			@php
				$seletect_store = '';
				if(session()->has('store')){
					$store 			= session('store');
					$seletect_store = $store['id'];
				}
			@endphp
			<select class="form-control" id="store_id" name="store_id">
				<option value="">All Stores</option>
				@foreach ($stores as $store)
				<option value="{{ $store['id'] }}" {{ ($store['id'] == $seletect_store) ? 'selected' : '' }}>{{ $store['name'] .'-'. $store['domain'] }}</option>
				@endforeach
			</select>
		</div>
	</div>
	<div class="modal-footer">
		<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
		<button type="submit" class="btn btn-primary">Save changes</button>
	</div>
</form>