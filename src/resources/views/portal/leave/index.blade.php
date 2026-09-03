@extends('hr::portal.layouts.app')

@section('title', 'My Leave - Employee Portal')
@section('page-title', 'My Leave')

@section('content')
@php
    $pendingCount = $leaves->filter(fn ($l) => strtolower($l->status ?? 'pending') === 'pending')->count();
    $approvedCount = $leaves->filter(fn ($l) => strtolower($l->status ?? '') === 'approved')->count();
@endphp

<div class="stat-grid">
    <div class="stat-tile accent-primary">
        <div class="stat-label">Total Requests</div>
        <div class="stat-value">{{ $leaves->count() }}</div>
    </div>
    <div class="stat-tile accent-success">
        <div class="stat-label">Approved</div>
        <div class="stat-value">{{ $approvedCount }}</div>
    </div>
    <div class="stat-tile accent-warning">
        <div class="stat-label">Pending</div>
        <div class="stat-value">{{ $pendingCount }}</div>
    </div>
</div>

<div class="card">
    <div class="card-title">
        Leave Requests
        <a href="javascript:void(0)" onclick="document.getElementById('leave-modal').classList.add('show')">+ Apply for Leave</a>
    </div>
    <div class="table-responsive-wrap">
        <table>
            <thead>
                <tr><th>Type</th><th>From</th><th>To</th><th>Status</th></tr>
            </thead>
            <tbody>
            @forelse($leaves as $leave)
                <tr>
                    <td>{{ optional($leave->leaveType)->name ?? '-' }}</td>
                    <td>{{ \Carbon\Carbon::parse($leave->leave_from)->format('d M Y') }}</td>
                    <td>{{ \Carbon\Carbon::parse($leave->leave_to)->format('d M Y') }}</td>
                    <td>
                        @php $status = strtolower($leave->status ?? 'pending'); @endphp
                        <span class="badge badge-{{ $status === 'approved' ? 'success' : ($status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($status) }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">
                        <div class="empty-state">
                            <div class="empty-icon">&#128197;</div>
                            <div class="empty-text">No leave requests found.</div>
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal-backdrop" id="leave-modal">
    <div class="modal-sheet">
        <div class="modal-title">
            Apply for Leave
            <a href="javascript:void(0)" class="close-link" onclick="document.getElementById('leave-modal').classList.remove('show')">&times;</a>
        </div>
        <form method="POST" action="{{ route('employee-portal.leave.store') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">Leave Type</label>
                <select name="leave_type_id" class="form-control" required>
                    <option value="">-- Select --</option>
                    @foreach($leaveTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">From</label>
                <input type="date" name="start_date" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">To</label>
                <input type="date" name="end_date" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Reason</label>
                <textarea name="reason" class="form-control" rows="3"></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Submit</button>
        </form>
    </div>
</div>
@endsection
