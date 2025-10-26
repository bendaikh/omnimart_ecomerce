@extends('master.back')

@section('content')

<!-- Start of Main Content -->
<div class="container-fluid">

	<!-- Page Heading -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-sm-flex align-items-center justify-content-between">
                <h3 class="mb-0"><b>{{ __('API Clients') }}</b></h3>
                <a href="{{ route('back.api-clients.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> {{ __('Add New Client') }}
                </a>
            </div>
        </div>
    </div>

	<!-- DataTales -->
	<div class="card shadow mb-4">
		<div class="card-body">
			@include('alerts.alerts')
			<div class="gd-responsive-table">
				<table class="table table-bordered table-striped" id="admin-table" width="100%" cellspacing="0">

					<thead>
						<tr>
							<th>{{ __('Name') }}</th>
							<th>{{ __('Domain') }}</th>
							<th>{{ __('Email') }}</th>
							<th>{{ __('Status') }}</th>
							<th>{{ __('Approved') }}</th>
							<th>{{ __('Transactions') }}</th>
							<th>{{ __('Last Used') }}</th>
							<th>{{ __('Actions') }}</th>
						</tr>
					</thead>

					<tbody>
              			@forelse($clients as $client)
                        <tr>
                            <td>{{ $client->name }}</td>
                            <td>{{ $client->domain }}</td>
                            <td>{{ $client->email }}</td>
                            <td>
                                @if($client->is_active)
                                    <span class="badge badge-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-danger">{{ __('Suspended') }}</span>
                                @endif
                            </td>
                            <td>
                                @if($client->is_approved)
                                    <span class="badge badge-success">{{ __('Approved') }}</span>
                                @else
                                    <span class="badge badge-warning">{{ __('Pending') }}</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('back.api-clients.transactions', $client->id) }}">
                                    {{ $client->transactions_count }}
                                </a>
                            </td>
                            <td>
                                @if($client->last_used_at)
                                    {{ $client->last_used_at->diffForHumans() }}
                                @else
                                    <span class="text-muted">{{ __('Never') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="{{ route('back.api-clients.show', $client->id) }}" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('back.api-clients.edit', $client->id) }}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="javascript:;" data-href="{{ route('back.api-clients.destroy', $client->id) }}" 
                                       class="btn btn-sm btn-danger" data-toggle="modal" data-target="#confirm-delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center">{{ __('No API clients found') }}</td>
                        </tr>
                        @endforelse
					</tbody>

				</table>
			</div>
            
            <!-- Pagination -->
            <div class="mt-3">
                {{ $clients->links() }}
            </div>
		</div>
	</div>

</div>

</div>
<!-- End of Main Content -->


{{-- DELETE MODAL --}}

<div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="confirm-deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">

		<!-- Modal Header -->
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalLabel">{{ __('Confirm Delete?') }}</h5>
          <button class="close" type="button" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
		</div>

		<!-- Modal Body -->
        <div class="modal-body">
			{{ __('You are going to delete this API Client. All related data will be removed.') }} {{ __('Do you want to delete it?') }}
		</div>

		<!-- Modal footer -->
        <div class="modal-footer">
			<button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
			<form action="" class="d-inline btn-ok" method="POST">

                @csrf

                @method('DELETE')

                <button type="submit" class="btn btn-danger">{{ __('Delete') }}</button>

			</form>
		</div>

      </div>
    </div>
</div>

@endsection

