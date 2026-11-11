@extends('printMaster2')

@section('title', 'Location Tracking Report - ' . $fromLabel . ' To ' . $toLabel)

@push('css')
<style>
.report-head { text-align:center; margin-bottom:10px; }
.report-head h3 { margin:0 0 2px; font-size:15px; }
.report-head p  { margin:0; font-size:11px; }
.sub-title { font-size:12px; font-weight:700; margin:8px 0 4px; }
.t { width:100%; border-collapse:collapse; margin-bottom:10px; font-size:10px; }
.t th, .t td { border:1px solid #555; padding:3px 5px; }
.t th { background:#eef1d4; text-align:center; }
.tc { text-align:center; }
.tr { text-align:right; }
.addr { font-size:9px; }
.offsite { color:#b91c1c; font-weight:700; }
.onsite { color:#15803d; font-weight:700; }
.missing { color:#b45309; }
.summary td { font-size:10px; }
.summary td b { font-size:12px; }

@media print {
    @page { size: A4 landscape; margin: 7mm; }
    body { margin: 0; }
}
</style>
@endpush

@section('contents')
@php
    $company = hr_factory('name') ?? 'Company Name';
    $address = hr_factory('address') ?? '';
    $s = $summary;
    $fmtDist = fn ($m) => $m === null ? '-' : ($m >= 1000 ? number_format($m / 1000, 2) . ' km' : $m . ' m');
    $place = fn ($p) => $p ? ($p['address'] ?: number_format($p['lat'], 5) . ', ' . number_format($p['lng'], 5)) : '-';
@endphp

<div class="report-head">
    @if(!blank(optional(general())->logo()))
        <img src="{{ asset(optional(general())->logo()) }}" alt="Logo" style="max-height:40px;margin-bottom:4px;">
    @endif
    <h3>{{ $company }}</h3>
    <p>{{ $address }}</p>
</div>

<div class="sub-title">Location Tracking Report &mdash; {{ $fromLabel }} to {{ $toLabel }}</div>

<table class="t summary">
    <tr>
        <td class="tc">Employees with location<br><b>{{ $s['tracked_employees'] }} / {{ $s['total_employees'] }}</b> ({{ $s['coverage'] }}%)</td>
        <td class="tc">Geo punches<br><b>{{ $s['punches'] }}</b> ({{ $s['punch_in'] }} in / {{ $s['punch_out'] }} out)</td>
        <td class="tc">On-site<br><b>{{ $s['onsite'] }}</b></td>
        <td class="tc">Off-site (&gt; {{ $radius }} m)<br><b>{{ $s['offsite'] }}</b> ({{ $s['offsite_pct'] }}%)</td>
        <td class="tc">Average punch-in<br><b>{{ $s['avg_in'] ?? '-' }}</b></td>
        <td class="tc">Late<br><b>{{ $s['late'] }}</b></td>
        <td class="tc">No punch-out<br><b>{{ $s['missing_out'] }}</b></td>
        <td class="tc">Avg in &rarr; out<br><b>{{ $fmtDist($s['avg_travel']) }}</b></td>
    </tr>
</table>

@if($rows->isEmpty())
    <p style="text-align:center;color:#888;padding:12px 0;">No geo-tagged punches found.</p>
@else
    <table class="t">
        <thead>
            <tr>
                <th>SL</th>
                <th>Date</th>
                <th>Emp. ID</th>
                <th>Name</th>
                <th>Department</th>
                <th>Designation</th>
                <th>In Time</th>
                <th>In Location</th>
                <th>From Base</th>
                <th>Out Time</th>
                <th>Out Location</th>
                <th>From Base</th>
                <th>Worked</th>
                <th>In &rarr; Out</th>
                <th>Site</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $i => $r)
                <tr>
                    <td class="tc">{{ $i + 1 }}</td>
                    <td class="tc">{{ \Carbon\Carbon::parse($r['date'])->format('d-M-Y') }}</td>
                    <td class="tc">{{ $r['employee_id'] }}</td>
                    <td>{{ $r['name'] }}</td>
                    <td>{{ $r['department'] ?? 'N/A' }}</td>
                    <td>{{ $r['designation'] ?? 'N/A' }}</td>
                    <td class="tc">{{ $r['in_time'] ?? '-' }}{{ $r['late'] ? ' (Late)' : '' }}</td>
                    <td class="addr">{{ $place($r['in']) }}</td>
                    <td class="tr {{ $r['in_offsite'] ? 'offsite' : '' }}">{{ $fmtDist($r['in_distance']) }}</td>
                    <td class="tc">{{ $r['out_time'] ?? '-' }}</td>
                    <td class="addr {{ $r['in_time'] && !$r['out_time'] ? 'missing' : '' }}">{{ $r['out'] ? $place($r['out']) : ($r['in_time'] && !$r['out_time'] ? 'Not punched out' : '-') }}</td>
                    <td class="tr {{ $r['out_offsite'] ? 'offsite' : '' }}">{{ $fmtDist($r['out_distance']) }}</td>
                    <td class="tc">{{ $r['worked_minute'] ? intdiv($r['worked_minute'], 60) . 'h ' . ($r['worked_minute'] % 60) . 'm' : '-' }}</td>
                    <td class="tr">{{ $fmtDist($r['travel']) }}</td>
                    <td class="tc {{ $r['in_offsite'] || $r['out_offsite'] ? 'offsite' : 'onsite' }}">{{ $r['in_offsite'] || $r['out_offsite'] ? 'Off-site' : 'On-site' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($byDepartment->isNotEmpty())
        <div class="sub-title">Department Summary</div>
        <table class="t" style="width:60%;">
            <thead>
                <tr><th>Department</th><th>Employees</th><th>Punches</th><th>Off-site</th></tr>
            </thead>
            <tbody>
                @foreach($byDepartment as $dept)
                    <tr>
                        <td>{{ $dept['name'] }}</td>
                        <td class="tc">{{ $dept['employees'] }}</td>
                        <td class="tc">{{ $dept['punches'] }}</td>
                        <td class="tc {{ $dept['offsite'] ? 'offsite' : '' }}">{{ $dept['offsite'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endif

<p style="font-size:9px;color:#666;">
    Distances are measured from {{ $base ? ($base['estimated'] ? 'the estimated base (median of punch-in points)' : 'the configured office location') : 'n/a' }};
    punches farther than {{ $radius }} m are marked Off-site.
</p>
@endsection
