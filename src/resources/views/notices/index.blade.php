@extends('admin.layouts.app')

@section('title')
<title>Notices</title>
@endsection

@section('contents')
<div class="flex-grow-1 p-4">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Notices</h4>
            <a href="javascript:void(0)" class="btn btn-primary btn-sm"
               data-toggle="modal" data-target="#CreateNoticeModal">
                + Publish Notice
            </a>
        </div>
        <div class="card-body">

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    {{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            @endif

            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1">Search</label>
                    <input type="text" name="search" value="{{ $request->search }}"
                           class="form-control form-control-sm" placeholder="Title...">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Status</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="">All</option>
                        <option value="1" @selected($request->status === '1')>Active</option>
                        <option value="0" @selected($request->status === '0')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary btn-sm w-100">Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="{{ route('hr-center.notices.index') }}" class="btn btn-light btn-sm w-100">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead class="thead-light">
                        <tr>
                            <th width="50">SL</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th width="110">Published</th>
                            <th width="90">Status</th>
                            <th width="100">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($notices as $index => $notice)
                        <tr>
                            <td>{{ $notices->firstItem() + $index }}</td>
                            <td>{{ $notice->title }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($notice->description, 80) }}</td>
                            <td>{{ optional($notice->published_at)->format('d M Y') }}</td>
                            <td class="text-center">
                                @if((int) $notice->status === 1)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-custom yellow btn-sm mr-1 btn-edit"
                                    data-id="{{ $notice->id }}"
                                    data-title="{{ $notice->title }}"
                                    data-description="{{ $notice->description }}"
                                    data-published="{{ optional($notice->published_at)->format('Y-m-d') }}"
                                    data-status="{{ $notice->status }}"
                                    data-toggle="modal" data-target="#EditNoticeModal">
                                    <i class="fa fa-edit"></i>
                                </button>
                                <form action="{{ route('hr-center.notices.destroy', $notice->id) }}"
                                      method="POST" class="d-inline"
                                      onsubmit="return confirm('Delete this notice?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-custom danger btn-xs">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">No notices found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $notices->links() }}

        </div>
    </div>
</div>

{{-- ===================== CREATE MODAL ===================== --}}
<div class="modal fade" id="CreateNoticeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form action="{{ route('hr-center.notices.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Publish Notice</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-2">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control form-control-sm" required>
                    </div>
                    <div class="form-group mb-2">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control form-control-sm" rows="4"></textarea>
                    </div>
                    <div class="form-group mb-2">
                        <label class="form-label">Published Date <span class="text-danger">*</span></label>
                        <input type="date" name="published_at" class="form-control form-control-sm" value="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-control form-control-sm" required>
                            <option value="1" selected>Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Publish</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ===================== EDIT MODAL ===================== --}}
<div class="modal fade" id="EditNoticeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form id="EditNoticeForm" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Notice</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-2">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="edit_title" class="form-control form-control-sm" required>
                    </div>
                    <div class="form-group mb-2">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-control form-control-sm" rows="4"></textarea>
                    </div>
                    <div class="form-group mb-2">
                        <label class="form-label">Published Date <span class="text-danger">*</span></label>
                        <input type="date" name="published_at" id="edit_published" class="form-control form-control-sm" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" id="edit_status" class="form-control form-control-sm" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm">Update</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('js')
<script>
(function () {
    document.querySelectorAll('.btn-edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('EditNoticeForm').action =
                '{{ url("admin/hr-center/notices") }}/' + this.dataset.id;

            document.getElementById('edit_title').value = this.dataset.title;
            document.getElementById('edit_description').value = this.dataset.description;
            document.getElementById('edit_published').value = this.dataset.published;
            document.getElementById('edit_status').value = this.dataset.status;
        });
    });
})();
</script>
@endpush
