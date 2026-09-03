@extends('hr::portal.layouts.app')

@section('title', 'Conveyance - Employee Portal')
@section('page-title', 'Conveyance Requests')

@section('content')
@php
    $pendingCount = $requests->filter(fn ($r) => strtolower($r->status ?? 'pending') === 'pending')->count();
    $totalAmount = $requests->sum('amount');
@endphp

<div class="stat-grid">
    <div class="stat-tile accent-primary">
        <div class="stat-label">Total Requests</div>
        <div class="stat-value">{{ $requests->count() }}</div>
    </div>
    <div class="stat-tile accent-warning">
        <div class="stat-label">Pending</div>
        <div class="stat-value">{{ $pendingCount }}</div>
    </div>
    <div class="stat-tile accent-success">
        <div class="stat-label">Total Amount</div>
        <div class="stat-value">৳{{ number_format($totalAmount, 0) }}</div>
    </div>
</div>

<div class="card">
    <div class="card-title">
        Conveyance Requests
        <a href="javascript:void(0)" onclick="document.getElementById('conveyance-modal').classList.add('show')">+ Add Request</a>
    </div>
    <div class="table-responsive-wrap">
        <table>
            <thead>
                <tr><th>#</th><th>From-To</th><th>Amount</th><th>Status</th><th>Payment</th></tr>
            </thead>
            <tbody>
            @forelse($requests as $req)
                <tr>
                    <td>{{ $req->request_no }}</td>
                    <td>{{ $req->from_location }} &rarr; {{ $req->to_location }}</td>
                    <td>{{ number_format($req->amount, 2) }}</td>
                    <td>
                        @php $status = strtolower($req->status ?? 'pending'); @endphp
                        <span class="badge badge-{{ $status === 'approved' ? 'success' : ($status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($status) }}</span>
                    </td>
                    <td>
                        @if($req->payment_status === 'unpaid')
                            <span class="badge badge-muted">Unpaid</span>
                        @elseif($req->payment_status === 'paid_cash')
                            <span class="badge badge-success">Paid (Cash)</span>
                        @else
                            <span class="badge badge-success">With Salary</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <div class="empty-icon">&#128666;</div>
                            <div class="empty-text">No conveyance requests found.</div>
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal-backdrop" id="conveyance-modal">
    <div class="modal-sheet">
        <div class="modal-title">
            Add Conveyance Request
            <a href="javascript:void(0)" class="close-link" onclick="document.getElementById('conveyance-modal').classList.remove('show')">&times;</a>
        </div>
        <form method="POST" action="{{ route('employee-portal.conveyance.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label class="form-label">From</label>
                <input type="text" name="from_location" class="form-control" placeholder="From location" required>
            </div>
            <div class="form-group">
                <label class="form-label">To</label>
                <input type="text" name="to_location" class="form-control" placeholder="To location" required>
            </div>
            <div class="form-group">
                <label class="form-label">Travel By</label>
                <select name="travel_by" class="form-control" required>
                    <option value="">-- Select --</option>
                    <option value="Bus">Bus</option>
                    <option value="CNG">CNG</option>
                    <option value="Rickshaw">Rickshaw</option>
                    <option value="Car">Car</option>
                    <option value="Train">Train</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Attachment</label>
                <input type="file" name="attachment" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Reason</label>
                <textarea name="reason" class="form-control" rows="3" placeholder="Write request details"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Amount</label>
                <input type="number" step="0.01" min="0" name="amount" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Submit</button>
        </form>
    </div>
</div>
@endsection
