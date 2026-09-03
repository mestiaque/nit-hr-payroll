@extends('hr::portal.layouts.app')

@section('title', 'My Attendance - Employee Portal')
@section('page-title', 'My Attendance')

@section('content')
<div class="card">
    <form method="GET" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <div class="form-group" style="margin-bottom:0; min-width:140px;">
            <label class="form-label">Month</label>
            <select name="month" class="form-control">
                @foreach($months as $num => $label)
                    <option value="{{ $num }}" @selected($num == $selectedMonth)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0; min-width:110px;">
            <label class="form-label">Year</label>
            <select name="year" class="form-control">
                @foreach($years as $y)
                    <option value="{{ $y }}" @selected($y == $selectedYear)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
    </form>
</div>

<div class="stat-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="stat-tile accent-success">
        <div class="stat-label">Present</div>
        <div class="stat-value">{{ $summary['present'] }}</div>
    </div>
    <div class="stat-tile accent-warning">
        <div class="stat-label">Late</div>
        <div class="stat-value">{{ $summary['late'] }}</div>
    </div>
    <div class="stat-tile" style="color:var(--danger);">
        <div class="stat-label">Absent</div>
        <div class="stat-value" style="color:var(--danger);">{{ $summary['absent'] }}</div>
    </div>
    <div class="stat-tile accent-primary">
        <div class="stat-label">Weekend / Holiday</div>
        <div class="stat-value">{{ $summary['leave_weekend_holiday'] }}</div>
    </div>
</div>

<div class="card">
    <div class="card-title">
        Attendance Report &mdash; {{ $months[$selectedMonth] }} {{ $selectedYear }}
    </div>
    <div class="table-responsive-wrap">
        <table>
            <thead>
                <tr><th>Date</th><th>Shift</th><th>Day</th><th>In</th><th>Out</th><th>OT</th><th>Status</th><th>Remarks</th></tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                @php
                    $otMinutes = (int) round(($row['compliance_ot'] ?? 0) * 60);
                    $badgeClass = match ($row['status_key']) {
                        'present' => 'badge-success',
                        'late' => 'badge-warning',
                        'punch_missing', 'pm', 'early_exit', 'eo' => 'badge-warning',
                        'late_and_early_exit', 'leo', 'late_and_punch_missing', 'lpm', 'absent' => 'badge-danger',
                        'leave' => 'badge-primary',
                        'weekend', 'holiday' => 'badge-muted',
                        default => 'badge-muted',
                    };
                    $statusLabel = match ($row['status_key']) {
                        'present' => 'Present',
                        'late' => 'Late',
                        'absent' => 'Absent',
                        'weekend' => 'Weekend',
                        'holiday' => 'Holiday',
                        'leave' => 'Leave',
                        'punch_missing', 'pm' => 'Punch Missing',
                        'early_exit', 'eo' => 'Early Exit',
                        'late_and_early_exit', 'leo' => 'Late & Early Exit',
                        'late_and_punch_missing', 'lpm' => 'Late & Punch Missing',
                        default => ucfirst(str_replace('_', ' ', $row['status_key'])),
                    };
                @endphp
                <tr class="{{ $row['date_carbon']->isToday() ? 'row-today' : '' }}">
                    <td>{{ $row['date_carbon']->format('d M Y') }}{{ $row['date_carbon']->isToday() ? ' (Today)' : '' }}</td>
                    <td>{{ $row['shift'] ?? '-' }}</td>
                    <td>{{ $row['date_carbon']->format('D') }}</td>
                    <td>{{ $row['in_time'] && $row['in_time'] !== '-' ? \Carbon\Carbon::parse($row['in_time'])->format('h:i A') : '-' }}</td>
                    <td>{{ $row['out_time'] && $row['out_time'] !== '-' ? \Carbon\Carbon::parse($row['out_time'])->format('h:i A') : '-' }}</td>
                    <td>{{ $otMinutes > 0 ? floor($otMinutes / 60) . 'h ' . ($otMinutes % 60) . 'm' : '-' }}</td>
                    <td><span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span></td>
                    <td>{{ $row['remarks'] ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <div class="empty-icon">&#128197;</div>
                            <div class="empty-text">No attendance records found.</div>
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
