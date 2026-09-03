@extends('admin.layouts.app')

@section('title')
<title>Conveyance Requests</title>
@endsection

@section('contents')
<div class="flex-grow-1 p-4">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Conveyance Requests</h4>
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
                           class="form-control form-control-sm" placeholder="Request No, Employee ID or Name...">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Status</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="">All</option>
                        <option value="pending" @selected($request->status === 'pending')>Pending</option>
                        <option value="approved" @selected($request->status === 'approved')>Approved</option>
                        <option value="rejected" @selected($request->status === 'rejected')>Rejected</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary btn-sm w-100">Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="{{ route('hr-center.conveyance-requests.index') }}" class="btn btn-light btn-sm w-100">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Request No</th>
                            <th>Employee</th>
                            <th>From &rarr; To</th>
                            <th>Travel By</th>
                            <th>Amount</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th width="160">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($requests as $index => $req)
                        <tr>
                            <td>{{ $requests->firstItem() + $index }}</td>
                            <td>{{ $req->request_no }}</td>
                            <td>{{ optional($req->employee)->employee_id }} &mdash; {{ optional($req->employee)->name }}</td>
                            <td>{{ $req->from_location }} &rarr; {{ $req->to_location }}</td>
                            <td>{{ $req->travel_by }}</td>
                            <td>{{ number_format($req->amount, 2) }}</td>
                            <td>{{ $req->reason }}</td>
                            <td>
                                @php $status = strtolower($req->status ?? 'pending'); @endphp
                                <span class="badge badge-{{ $status === 'approved' ? 'success' : ($status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($status) }}</span>
                            </td>
                            <td>
                                @if($req->payment_status === 'unpaid')
                                    <span class="badge badge-secondary">Unpaid</span>
                                @elseif($req->payment_status === 'paid_cash')
                                    <span class="badge badge-success">Paid (Cash)</span>
                                @else
                                    <span class="badge badge-info">With Salary</span>
                                @endif
                            </td>
                            <td>
                                @if($req->status === 'pending')
                                    <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#ApproveModal_{{ $req->id }}">Approve</button>
                                    <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#RejectModal_{{ $req->id }}">Reject</button>
                                @else
                                    <span class="text-muted">&mdash;</span>
                                @endif
                            </td>
                        </tr>

                        {{-- Approve modal --}}
                        <div class="modal fade" id="ApproveModal_{{ $req->id }}" tabindex="-1" role="dialog">
                            <div class="modal-dialog" role="document">
                                <form method="POST" action="{{ route('hr-center.conveyance-requests.approve', $req->id) }}">
                                    @csrf
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Approve Conveyance Request — {{ $req->request_no }}</h5>
                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="form-group mb-2">
                                                <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                                                <select name="payment_method" class="form-control form-control-sm" required>
                                                    <option value="cash">Cash (Paid Now)</option>
                                                    <option value="salary">Add to Next Salary</option>
                                                </select>
                                            </div>
                                            <div class="form-group mb-0">
                                                <label class="form-label">Remark</label>
                                                <textarea name="admin_remark" class="form-control form-control-sm" rows="2"></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        {{-- Reject modal --}}
                        <div class="modal fade" id="RejectModal_{{ $req->id }}" tabindex="-1" role="dialog">
                            <div class="modal-dialog" role="document">
                                <form method="POST" action="{{ route('hr-center.conveyance-requests.reject', $req->id) }}">
                                    @csrf
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Reject Conveyance Request — {{ $req->request_no }}</h5>
                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="form-group mb-0">
                                                <label class="form-label">Remark</label>
                                                <textarea name="admin_remark" class="form-control form-control-sm" rows="2"></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted">No conveyance requests found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{ $requests->links() }}

        </div>
    </div>
</div>
@endsection
