@extends('hr::portal.layouts.app')

@section('title', 'Dashboard - Employee Portal')
@section('page-title', 'Dashboard')

@section('content')
@php
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
@endphp

<div class="content-grid">
    <div>
        <div class="card dashboard-greeting-card">
            <div style="display:flex; align-items:center; gap:16px;">
                <img src="{{ asset($employee->image('sm')) }}" alt="photo" class="dashboard-avatar">
                <div>
                    <div style="font-size:12px; color:var(--muted); font-weight:600; margin-bottom:2px;">{{ $greeting }},</div>
                    <div style="font-weight:800; font-size:18px;">{{ $employee->name }}</div>
                    <div style="font-size:13px; color:var(--muted); margin-top:2px;">
                        {{ $employee->employee_id }} &middot; {{ optional($employee->designation)->name }}
                    </div>
                </div>
            </div>
        </div>

        @if($latestNotice)
            <div class="card latest-notice-card">
                <div class="card-title">
                    <span><i class="fa-solid fa-bullhorn" style="color:var(--primary); margin-right:6px;"></i>Latest Notice</span>
                    <a href="{{ route('employee-portal.notices.index') }}">View All &rarr;</a>
                </div>
                <div class="latest-notice-title">{{ $latestNotice->title }}</div>
                @if($latestNotice->description)
                    <div class="latest-notice-body">{{ \Illuminate\Support\Str::limit($latestNotice->description, 150) }}</div>
                @endif
                <div class="latest-notice-date">{{ optional($latestNotice->published_at)->format('d M Y') }}</div>
            </div>
        @endif

        <div class="card">
            <div class="card-title">
                <span><i class="fa-solid fa-clock" style="color:var(--primary); margin-right:6px;"></i>Today's Attendance</span>
                <a href="{{ route('employee-portal.attendance.index') }}">View All &rarr;</a>
            </div>

            <div class="attendance-time-row">
                <div class="attendance-time-box">
                    <div class="stat-label"><i class="fa-solid fa-right-to-bracket" style="margin-right:4px;"></i>Check In</div>
                    <div class="attendance-time-value {{ $today && $today->in_time ? 'text-success' : '' }}">
                        {{ $today && $today->in_time ? \Carbon\Carbon::parse($today->in_time)->format('h:i A') : '--:--' }}
                    </div>
                </div>
                <div class="attendance-time-box">
                    <div class="stat-label"><i class="fa-solid fa-right-from-bracket" style="margin-right:4px;"></i>Check Out</div>
                    <div class="attendance-time-value {{ $today && $today->out_time ? 'text-success' : '' }}">
                        {{ $today && $today->out_time ? \Carbon\Carbon::parse($today->out_time)->format('h:i A') : '--:--' }}
                    </div>
                </div>
            </div>

            @php
                $alreadyIn = $today && $today->in_time;
                $alreadyOut = $today && $today->out_time;
            @endphp

            <form id="punch-form" method="POST" action="{{ route('employee-portal.attendance.punch') }}">
                @csrf
                <input type="hidden" name="latitude" id="punch-lat">
                <input type="hidden" name="longitude" id="punch-lng">
                <button type="submit" class="btn btn-block btn-punch {{ !$alreadyIn ? 'btn-punch-in' : ($alreadyOut ? 'btn-punch-done' : 'btn-punch-out') }}" id="punch-btn" @if($alreadyIn && $alreadyOut) disabled @endif>
                    @if(!$alreadyIn)
                        <i class="fa-solid fa-fingerprint"></i> Punch In
                    @elseif(!$alreadyOut)
                        <i class="fa-solid fa-fingerprint"></i> Punch Out
                    @else
                        <i class="fa-solid fa-circle-check"></i> Completed for Today
                    @endif
                </button>
            </form>

            @if($today && ($today->latitude || $today->out_latitude))
                @php
                    $points = [];
                    if ($today->latitude) {
                        $points['in'] = ['label' => 'Check-in', 'color' => '#28a745', 'lat' => $today->latitude, 'lng' => $today->longitude,
                            'time' => $today->in_time ? \Carbon\Carbon::parse($today->in_time)->format('h:i A') : ''];
                    }
                    if ($today->out_latitude) {
                        $points['out'] = ['label' => 'Check-out', 'color' => '#dc3545', 'lat' => $today->out_latitude, 'lng' => $today->out_longitude,
                            'time' => $today->out_time ? \Carbon\Carbon::parse($today->out_time)->format('h:i A') : ''];
                    }
                    $active = array_key_first($points);
                @endphp
                <style>
                    .punch-map-btn { border:1px solid var(--c); color:var(--c); background:#fff; }
                    .punch-map-btn.is-active { background:var(--c); color:#fff; }
                </style>
                <div style="margin-top:14px;">
                    <div style="display:flex; gap:8px; margin-bottom:8px; flex-wrap:wrap;">
                        @foreach($points as $key => $p)
                            <button type="button" class="btn btn-sm punch-map-btn js-punch-map {{ $key === $active ? 'is-active' : '' }}"
                                style="--c:{{ $p['color'] }};" data-lat="{{ $p['lat'] }}" data-lng="{{ $p['lng'] }}"
                                data-label="{{ $p['label'] }}" data-time="{{ $p['time'] }}" data-color="{{ $p['color'] }}">
                                <i class="fa-solid fa-location-dot"></i> {{ $p['label'] }} location
                            </button>
                        @endforeach
                    </div>
                    <div id="punch-map-caption" style="font-size:13px; font-weight:600; margin-bottom:6px; color:{{ $points[$active]['color'] }};">
                        <i class="fa-solid fa-location-dot"></i>
                        Showing: <span>{{ $points[$active]['label'] }} location{{ $points[$active]['time'] ? ' · ' . $points[$active]['time'] : '' }}</span>
                    </div>
                    <iframe id="punch-map" src="https://maps.google.com/maps?q={{ $points[$active]['lat'] }},{{ $points[$active]['lng'] }}&z=17&output=embed"
                        style="width:100%; height:220px; border:2px solid {{ $points[$active]['color'] }}; border-radius:8px;" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    <a id="punch-map-link" href="https://www.google.com/maps/search/?api=1&query={{ $points[$active]['lat'] }},{{ $points[$active]['lng'] }}" target="_blank" rel="noopener" style="font-size:12px;">Open in Google Maps</a>
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-title"><i class="fa-solid fa-calendar-week" style="color:var(--primary); margin-right:6px;"></i>This Week</div>
            <div class="week-strip">
                @foreach($weekStrip as $day)
                    <div class="week-day {{ $day['is_today'] ? 'week-day-today' : '' }}">
                        <div class="week-day-label">{{ $day['label'] }}</div>
                        <div class="week-day-dot week-day-{{ $day['status'] }}">
                            @switch($day['status'])
                                @case('present')
                                    <i class="fa-solid fa-check"></i>
                                    @break
                                @case('late')
                                    <i class="fa-solid fa-clock"></i>
                                    @break
                                @case('absent')
                                    <i class="fa-solid fa-xmark"></i>
                                    @break
                                @case('weekend')
                                    <i class="fa-solid fa-mug-hot"></i>
                                    @break
                                @default
                                    {{ $day['day'] }}
                            @endswitch
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-title"><i class="fa-solid fa-chart-simple" style="color:var(--primary); margin-right:6px;"></i>This Month</div>
            <div class="stat-grid" style="grid-template-columns: 1fr 1fr 1fr; margin-bottom:0;">
                <div class="stat-tile accent-success">
                    <div class="stat-tile-icon"><i class="fa-solid fa-user-check"></i></div>
                    <div>
                        <div class="stat-label">Present</div>
                        <div class="stat-value">{{ $stats['present_days'] }}</div>
                    </div>
                </div>
                <div class="stat-tile accent-warning">
                    <div class="stat-tile-icon"><i class="fa-solid fa-clock"></i></div>
                    <div>
                        <div class="stat-label">Late</div>
                        <div class="stat-value">{{ $stats['late_days'] }}</div>
                    </div>
                </div>
                <div class="stat-tile accent-primary">
                    <div class="stat-tile-icon"><i class="fa-solid fa-umbrella-beach"></i></div>
                    <div>
                        <div class="stat-label">Leave</div>
                        <div class="stat-value">{{ $stats['leave_days'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-title">
                <span><i class="fa-solid fa-calendar-check" style="color:var(--primary); margin-right:6px;"></i>Leave Summary</span>
                <a href="{{ route('employee-portal.leave.index') }}">View All &rarr;</a>
            </div>
            @forelse($leaveSummary as $leave)
                <div class="leave-progress-row">
                    <div class="leave-progress-label">
                        <span>{{ $leave['name'] }}</span>
                        <span>{{ $leave['taken_display'] }} / {{ $leave['allocated'] }} days</span>
                    </div>
                    <div class="leave-progress-track">
                        <div class="leave-progress-fill" style="width: {{ $leave['percent'] }}%;"></div>
                    </div>
                </div>
            @empty
                <p style="font-size:13px; color:var(--muted); margin:0;">No leave types configured.</p>
            @endforelse
        </div>

        <div class="card">
            <div class="card-title">
                <span><i class="fa-solid fa-route" style="color:var(--primary); margin-right:6px;"></i>Conveyance</span>
                <a href="{{ route('employee-portal.conveyance.index') }}">View All &rarr;</a>
            </div>
            @forelse($recentConveyance as $conv)
                @php $status = strtolower($conv->status ?? 'pending'); @endphp
                <div class="conveyance-row">
                    <div>
                        <div style="font-weight:700; font-size:13px;">{{ $conv->from_location }} &rarr; {{ $conv->to_location }}</div>
                        <div style="font-size:12px; color:var(--muted);">{{ $conv->request_no }} &middot; &#2547;{{ number_format($conv->amount, 0) }}</div>
                    </div>
                    <span class="badge badge-{{ $status === 'approved' ? 'success' : ($status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($status) }}</span>
                </div>
            @empty
                <p style="font-size:13px; color:var(--muted); margin:0;">No conveyance requests yet.</p>
            @endforelse
        </div>

        <div class="card">
            <div class="card-title"><i class="fa-solid fa-bolt" style="color:var(--primary); margin-right:6px;"></i>Quick Actions</div>
            <a href="{{ route('employee-portal.leave.index') }}" class="quick-action-link">
                <span><i class="fa-solid fa-calendar-plus" style="margin-right:6px; color:var(--primary);"></i>Apply for Leave</span>
                @if($stats['pending_leaves'] > 0)
                    <span class="badge badge-warning">{{ $stats['pending_leaves'] }} pending</span>
                @endif
            </a>
            <a href="{{ route('employee-portal.conveyance.index') }}" class="quick-action-link">
                <span><i class="fa-solid fa-route" style="margin-right:6px; color:var(--primary);"></i>Add Conveyance Request</span>
                @if($stats['pending_conveyance'] > 0)
                    <span class="badge badge-warning">{{ $stats['pending_conveyance'] }} pending</span>
                @endif
            </a>
            <a href="{{ route('employee-portal.attendance.index') }}" class="quick-action-link">
                <span><i class="fa-solid fa-clock-rotate-left" style="margin-right:6px; color:var(--primary);"></i>View Attendance History</span>
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('punch-form').addEventListener('submit', function (e) {
    if (document.getElementById('punch-lat').value) return; // already resolved
    e.preventDefault();
    var form = this;
    var btn = document.getElementById('punch-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Getting location...';

    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser.');
        btn.disabled = false;
        return;
    }

    var originalLabel = btn.dataset.label;
    navigator.geolocation.getCurrentPosition(function (position) {
        document.getElementById('punch-lat').value = position.coords.latitude.toFixed(7);
        document.getElementById('punch-lng').value = position.coords.longitude.toFixed(7);
        form.submit();
    }, function (error) {
        var messages = {
            1: 'Location permission is required to punch attendance. Please allow location access and try again.',
            2: 'Your location could not be found. Please turn on GPS / location and try again.',
            3: 'Getting your location took too long. Please try again.'
        };
        alert(messages[error.code] || 'Could not get your location.');
        btn.disabled = false;
        btn.innerHTML = originalLabel;
    }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 });
});

document.addEventListener('DOMContentLoaded', function () {
    var b = document.getElementById('punch-btn');
    if (b) b.dataset.label = b.innerHTML;
});
document.querySelectorAll('.js-punch-map').forEach(function (b) {
    b.addEventListener('click', function () {
        var d = this.dataset, q = d.lat + ',' + d.lng;
        var frame = document.getElementById('punch-map');
        frame.src = 'https://maps.google.com/maps?q=' + q + '&z=17&output=embed';
        frame.style.borderColor = d.color;
        document.getElementById('punch-map-link').href = 'https://www.google.com/maps/search/?api=1&query=' + q;
        var caption = document.getElementById('punch-map-caption');
        caption.style.color = d.color;
        caption.querySelector('span').textContent = d.label + ' location' + (d.time ? ' · ' + d.time : '');
        document.querySelectorAll('.js-punch-map').forEach(function (x) { x.classList.toggle('is-active', x === b); });
    });
});
</script>
@endpush
