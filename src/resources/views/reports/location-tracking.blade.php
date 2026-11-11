@extends('admin.layouts.app')

@section('title')
<title>Location Tracking Report</title>
@endsection

@push('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.Default.min.css">
<style>
.lt-wrap { --lt-in:#16a34a; --lt-out:#dc2626; --lt-base:#2563eb; --lt-warn:#f59e0b; --lt-ink:#0f172a; --lt-muted:#64748b; --lt-line:#e2e8f0; --lt-soft:#f8fafc; color:var(--lt-ink); }
.lt-wrap .card { border:1px solid var(--lt-line); border-radius:12px; box-shadow:0 1px 2px rgba(15,23,42,.04); }
.lt-hero { background:linear-gradient(120deg,#0f172a 0%,#1e3a8a 55%,#0e7490 100%); color:#fff; border-radius:14px; padding:18px 22px; display:flex; flex-wrap:wrap; gap:14px; align-items:center; justify-content:space-between; }
.lt-hero h4 { margin:0; font-weight:700; color:#fff; }
.lt-hero .lt-sub { opacity:.8; font-size:13px; margin-top:2px; }
.lt-hero .btn { border-radius:8px; }
.lt-live { display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,.12); padding:4px 10px; border-radius:20px; font-size:12px; }
.lt-live .dot { width:8px; height:8px; border-radius:50%; background:#22c55e; box-shadow:0 0 0 0 rgba(34,197,94,.7); animation:ltPulse 1.6s infinite; }
.lt-live.off .dot { background:#94a3b8; animation:none; }
@keyframes ltPulse { 0%{box-shadow:0 0 0 0 rgba(34,197,94,.7)} 70%{box-shadow:0 0 0 8px rgba(34,197,94,0)} 100%{box-shadow:0 0 0 0 rgba(34,197,94,0)} }

.lt-filter label { font-size:12px; color:var(--lt-muted); margin-bottom:3px; }

.lt-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:12px; }
.lt-kpi { background:#fff; border:1px solid var(--lt-line); border-radius:12px; padding:14px 16px; display:flex; gap:12px; align-items:center; }
.lt-kpi .ic { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.lt-kpi .v { font-size:22px; font-weight:700; line-height:1.1; }
.lt-kpi .l { font-size:12px; color:var(--lt-muted); }
.lt-kpi .s { font-size:11px; color:var(--lt-muted); margin-top:2px; }
.ic-blue { background:#dbeafe; color:#1d4ed8; } .ic-green { background:#dcfce7; color:#15803d; } .ic-red { background:#fee2e2; color:#b91c1c; }
.ic-amber { background:#fef3c7; color:#b45309; } .ic-violet { background:#ede9fe; color:#6d28d9; } .ic-cyan { background:#cffafe; color:#0e7490; } .ic-slate { background:#f1f5f9; color:#334155; }
.lt-ring { width:42px; height:42px; flex-shrink:0; }

.lt-main { display:grid; grid-template-columns:340px 1fr; gap:12px; }
@media (max-width: 991px) { .lt-main { grid-template-columns:1fr; } }
.lt-list { display:flex; flex-direction:column; height:600px; }
.lt-list-head { padding:12px; border-bottom:1px solid var(--lt-line); }
.lt-chips { display:flex; gap:6px; flex-wrap:wrap; margin-top:8px; }
.lt-chip { border:1px solid var(--lt-line); background:#fff; border-radius:20px; padding:2px 10px; font-size:12px; cursor:pointer; color:var(--lt-muted); }
.lt-chip.active { background:var(--lt-ink); border-color:var(--lt-ink); color:#fff; }
.lt-list-body { overflow-y:auto; flex:1; }
.lt-emp { display:flex; gap:10px; padding:10px 12px; border-bottom:1px solid var(--lt-line); cursor:pointer; align-items:center; transition:background .15s; }
.lt-emp:hover { background:var(--lt-soft); }
.lt-emp.active { background:#eff6ff; box-shadow:inset 3px 0 0 var(--lt-base); }
.lt-avatar { width:38px; height:38px; border-radius:50%; object-fit:cover; flex-shrink:0; background:#e2e8f0; }
.lt-emp .nm { font-weight:600; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.lt-emp .meta { font-size:11px; color:var(--lt-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.lt-emp .rt { margin-left:auto; text-align:right; flex-shrink:0; }
.lt-badge { display:inline-block; font-size:10px; font-weight:600; padding:1px 7px; border-radius:10px; }
.b-on { background:#dcfce7; color:#15803d; } .b-off { background:#fee2e2; color:#b91c1c; } .b-miss { background:#fef3c7; color:#b45309; } .b-late { background:#ede9fe; color:#6d28d9; }

.lt-map-card { position:relative; height:600px; overflow:hidden; }
#ltMap { position:absolute; inset:0; }
.lt-map-tools { position:absolute; top:10px; right:10px; z-index:500; background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.15); padding:8px 10px; font-size:12px; }
.lt-map-tools label { display:flex; align-items:center; gap:6px; margin:2px 0; cursor:pointer; color:var(--lt-ink); }
.lt-map-tools hr { margin:6px 0; }
.lt-legend { position:absolute; bottom:12px; left:12px; z-index:500; background:rgba(255,255,255,.95); border-radius:10px; padding:6px 10px; font-size:11px; display:flex; gap:12px; box-shadow:0 2px 10px rgba(0,0,0,.12); }
.lt-legend i { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:4px; vertical-align:-1px; }
.lt-empty-map { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; flex-direction:column; color:var(--lt-muted); z-index:400; background:rgba(248,250,252,.85); }

.lt-pin { width:30px; height:30px; border-radius:50% 50% 50% 0; transform:rotate(-45deg); border:2px solid #fff; box-shadow:0 2px 6px rgba(0,0,0,.35); display:flex; align-items:center; justify-content:center; }
.lt-pin span { transform:rotate(45deg); color:#fff; font-size:10px; font-weight:700; }
.lt-pin.in { background:var(--lt-in); } .lt-pin.out { background:var(--lt-out); } .lt-pin.off { outline:3px solid var(--lt-warn); outline-offset:1px; }
.lt-base-pin { width:34px; height:34px; border-radius:50%; background:var(--lt-base); color:#fff; display:flex; align-items:center; justify-content:center; border:3px solid #fff; box-shadow:0 2px 8px rgba(0,0,0,.35); }

.lt-pop { min-width:240px; font-size:12px; }
.lt-pop .hd { display:flex; gap:8px; align-items:center; margin-bottom:6px; }
.lt-pop .hd img { width:40px; height:40px; border-radius:50%; object-fit:cover; }
.lt-pop .hd b { font-size:13px; display:block; }
.lt-pop .row2 { display:flex; gap:6px; margin:3px 0; }
.lt-pop .row2 .k { color:var(--lt-muted); width:62px; flex-shrink:0; }
.lt-pop a { font-weight:600; }

.lt-bars { display:flex; align-items:flex-end; gap:3px; height:150px; padding-top:10px; }
.lt-bars .b { flex:1; background:linear-gradient(180deg,#3b82f6,#1d4ed8); border-radius:4px 4px 0 0; position:relative; min-height:2px; }
.lt-bars .b.zero { background:#e2e8f0; }
.lt-bars .b:hover::after { content:attr(data-tip); position:absolute; bottom:100%; left:50%; transform:translateX(-50%); background:var(--lt-ink); color:#fff; font-size:10px; padding:2px 6px; border-radius:4px; white-space:nowrap; margin-bottom:4px; }
.lt-bar-axis { display:flex; gap:3px; font-size:9px; color:var(--lt-muted); }
.lt-bar-axis span { flex:1; text-align:center; }
.lt-hbar { margin-bottom:9px; font-size:12px; }
.lt-hbar .top { display:flex; justify-content:space-between; margin-bottom:3px; }
.lt-hbar .track { height:8px; background:#f1f5f9; border-radius:5px; overflow:hidden; display:flex; }
.lt-hbar .fill { background:#3b82f6; } .lt-hbar .fill.off { background:#ef4444; }

.lt-table { font-size:12px; }
.lt-table th { background:var(--lt-soft); font-weight:600; white-space:nowrap; position:sticky; top:0; z-index:1; }
.lt-table td { vertical-align:middle; }
.lt-table .addr { max-width:240px; color:var(--lt-muted); font-size:11px; }
.lt-table tr { cursor:pointer; }
.lt-section-title { font-weight:700; font-size:14px; margin:0; }
.lt-section-title small { color:var(--lt-muted); font-weight:400; }
</style>
@endpush

@section('contents')
@include('hr::partials.report-loader')
@php
    $s = $summary;
    $isToday = $to >= now()->toDateString() && $from <= now()->toDateString();
    $fmtDist = fn ($m) => $m === null ? '—' : ($m >= 1000 ? number_format($m / 1000, 2) . ' km' : $m . ' m');
@endphp
<div class="flex-grow-1 p-4 lt-wrap">

    {{-- Hero --}}
    <div class="lt-hero mb-3">
        <div>
            <h4><i class="fa-solid fa-location-dot me-2"></i>Location Tracking Report</h4>
            <div class="lt-sub">
                {{ $fromLabel }}@if($from !== $to) &rarr; {{ $toLabel }}@endif
                · Geo-tagged punches from the employee portal
                · Reference: {{ $base ? ($base['estimated'] ? 'estimated base (median of punches)' : 'office location') : 'n/a' }}, {{ $radius }} m radius
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            @if($isToday)
                <span class="lt-live off" id="ltLive"><span class="dot"></span><span id="ltLiveText">Live off</span></span>
                <button type="button" class="btn btn-sm btn-light" id="ltLiveBtn"><i class="fa-solid fa-satellite-dish"></i> Live Track</button>
            @endif
            <button type="button" class="btn btn-sm btn-light js-lt-export" data-xlsx="0"><i class="fa-solid fa-print"></i> Print</button>
            <button type="button" class="btn btn-sm btn-success js-lt-export" data-xlsx="1"><i class="fa-solid fa-file-excel"></i> Excel</button>
            <a href="{{ route('hr-center.reports.index') }}" class="btn btn-sm btn-outline-light">Back</a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-3">
        <div class="card-body py-3">
            <form method="get" action="{{ route('hr-center.reports.location-tracking') }}" id="ltFilterForm" class="lt-filter">
                <div class="row g-2">
                    <div class="col-md-2">
                        <label>Date From</label>
                        <input type="date" name="from" class="form-control form-control-sm" value="{{ $from }}">
                    </div>
                    <div class="col-md-2">
                        <label>Date To</label>
                        <input type="date" name="to" class="form-control form-control-sm" value="{{ $to }}">
                    </div>
                    <div class="col-md-2">
                        <label>Employee ID(s)</label>
                        <input type="text" name="employee_ids" class="form-control form-control-sm" value="{{ $request->employee_ids }}" placeholder="A00001,A00002">
                    </div>
                    <div class="col-md-2">
                        <label>Department</label>
                        <select name="department[]" class="form-control form-control-sm select2" multiple>
                            @foreach($options['departments'] as $item)
                                <option value="{{ $item->id }}" @selected(in_array((string)$item->id, (array)$request->department))>{{ $item->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>Section</label>
                        <select name="section[]" class="form-control form-control-sm select2" multiple>
                            @foreach($options['sections'] as $item)
                                <option value="{{ $item->id }}" @selected(in_array((string)$item->id, (array)$request->section))>{{ $item->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>Designation</label>
                        <select name="designation[]" class="form-control form-control-sm select2" multiple>
                            @foreach($options['designations'] as $item)
                                <option value="{{ $item->id }}" @selected(in_array((string)$item->id, (array)$request->designation))>{{ $item->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>Site</label>
                        <select name="site" class="form-control form-control-sm">
                            <option value="">All punches</option>
                            <option value="onsite" @selected($request->site === 'onsite')>On-site only</option>
                            <option value="offsite" @selected($request->site === 'offsite')>Off-site only</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>Status</label>
                        <select name="employee_status[]" class="form-control form-control-sm select2" multiple>
                            <option value="regular" @selected(in_array('regular', (array)$request->employee_status))>Regular</option>
                            <option value="lefty" @selected(in_array('lefty', (array)$request->employee_status))>Lefty</option>
                            <option value="resign" @selected(in_array('resign', (array)$request->employee_status))>Resign</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm js-lt-quick" data-range="today">Today</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm js-lt-quick" data-range="yesterday">Yesterday</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm js-lt-quick" data-range="week">Last 7 days</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm js-lt-quick" data-range="month">This month</button>
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <a href="{{ route('hr-center.reports.location-tracking') }}" class="btn btn-light btn-sm flex-fill"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                        <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="fa-solid fa-filter"></i> Apply Filter</button>
                    </div>
                </div>
            </form>
            {{-- Hidden twin of the filter form that opens the printable / Excel version --}}
            <form method="get" action="{{ route('hr-center.reports.location-tracking-print') }}" target="_blank" id="ltExportForm" class="d-none"></form>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="lt-kpis mb-3">
        <div class="lt-kpi">
            <svg class="lt-ring" viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e2e8f0" stroke-width="4"/>
                <circle cx="18" cy="18" r="15.9" fill="none" stroke="#2563eb" stroke-width="4" stroke-linecap="round"
                        stroke-dasharray="{{ min(100, $s['coverage']) }} 100" transform="rotate(-90 18 18)"/>
            </svg>
            <div>
                <div class="v">{{ $s['tracked_employees'] }}<small class="text-muted" style="font-size:13px;"> / {{ $s['total_employees'] }}</small></div>
                <div class="l">Employees with location</div>
                <div class="s">{{ $s['coverage'] }}% coverage</div>
            </div>
        </div>
        <div class="lt-kpi">
            <div class="ic ic-cyan"><i class="fa-solid fa-map-pin"></i></div>
            <div>
                <div class="v">{{ $s['punches'] }}</div>
                <div class="l">Geo punches</div>
                <div class="s"><span style="color:var(--lt-in)">{{ $s['punch_in'] }} in</span> · <span style="color:var(--lt-out)">{{ $s['punch_out'] }} out</span></div>
            </div>
        </div>
        <div class="lt-kpi">
            <div class="ic ic-green"><i class="fa-solid fa-building-circle-check"></i></div>
            <div>
                <div class="v">{{ $s['onsite'] }}</div>
                <div class="l">On-site punches</div>
                <div class="s">within {{ $radius }} m of base</div>
            </div>
        </div>
        <div class="lt-kpi">
            <div class="ic ic-red"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div>
                <div class="v">{{ $s['offsite'] }}</div>
                <div class="l">Off-site punches</div>
                <div class="s">{{ $s['offsite_pct'] }}% of all punches</div>
            </div>
        </div>
        <div class="lt-kpi">
            <div class="ic ic-blue"><i class="fa-regular fa-clock"></i></div>
            <div>
                <div class="v">{{ $s['avg_in'] ?? '—' }}</div>
                <div class="l">Average punch-in</div>
                <div class="s">{{ $s['late'] }} late arrival(s)</div>
            </div>
        </div>
        <div class="lt-kpi">
            <div class="ic ic-amber"><i class="fa-solid fa-person-walking-arrow-right"></i></div>
            <div>
                <div class="v">{{ $s['missing_out'] }}</div>
                <div class="l">No punch-out yet</div>
                <div class="s">in without out</div>
            </div>
        </div>
        <div class="lt-kpi">
            <div class="ic ic-violet"><i class="fa-solid fa-route"></i></div>
            <div>
                <div class="v">{{ $fmtDist($s['avg_travel']) }}</div>
                <div class="l">Avg in &rarr; out distance</div>
                <div class="s">farthest from base: {{ $fmtDist($s['max_distance']) }}</div>
            </div>
        </div>
        <div class="lt-kpi">
            <div class="ic ic-slate"><i class="fa-solid fa-signal"></i></div>
            <div style="min-width:0;">
                @if($s['last_punch'])
                    <div class="v" style="font-size:16px;">{{ $s['last_punch']['out_time'] ?? $s['last_punch']['in_time'] }}</div>
                    <div class="l">Latest punch</div>
                    <div class="s text-truncate">{{ $s['last_punch']['name'] }}</div>
                @else
                    <div class="v">—</div>
                    <div class="l">Latest punch</div>
                @endif
            </div>
        </div>
    </div>

    {{-- List + map --}}
    <div class="lt-main mb-3">
        <div class="card lt-list">
            <div class="lt-list-head">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <p class="lt-section-title">Employees <small id="ltEmpCount"></small></p>
                    <button type="button" class="btn btn-link btn-sm p-0" id="ltShowAll">Show all</button>
                </div>
                <input type="search" id="ltSearch" class="form-control form-control-sm" placeholder="Search name, ID, department...">
                <div class="lt-chips">
                    <span class="lt-chip active" data-filter="all">All</span>
                    <span class="lt-chip" data-filter="offsite">Off-site</span>
                    <span class="lt-chip" data-filter="missing">No punch-out</span>
                    <span class="lt-chip" data-filter="late">Late</span>
                </div>
            </div>
            <div class="lt-list-body" id="ltEmpList"></div>
        </div>

        <div class="card lt-map-card">
            <div id="ltMap"></div>
            @if($rows->isEmpty())
                <div class="lt-empty-map">
                    <i class="fa-solid fa-map-location-dot fa-3x mb-2"></i>
                    <div>No geo-tagged punches for this filter.</div>
                </div>
            @endif
            <div class="lt-map-tools">
                <label><input type="checkbox" id="ltLayerIn" checked> <span style="color:var(--lt-in)">●</span> Punch-in</label>
                <label><input type="checkbox" id="ltLayerOut" checked> <span style="color:var(--lt-out)">●</span> Punch-out</label>
                <label><input type="checkbox" id="ltLayerPath" checked> In &rarr; out path</label>
                <label><input type="checkbox" id="ltLayerBase" checked> <span style="color:var(--lt-base)">●</span> Base radius</label>
                <label><input type="checkbox" id="ltCluster" checked> Cluster markers</label>
                <hr>
                <label><input type="radio" name="ltTile" value="street" checked> Street</label>
                <label><input type="radio" name="ltTile" value="satellite"> Satellite</label>
            </div>
            <div class="lt-legend">
                <span><i style="background:var(--lt-in)"></i>In</span>
                <span><i style="background:var(--lt-out)"></i>Out</span>
                <span><i style="background:#fff;border:2px solid var(--lt-warn)"></i>Off-site</span>
                <span><i style="background:var(--lt-base)"></i>Base</span>
            </div>
        </div>
    </div>

    {{-- Insights --}}
    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <div class="card h-100"><div class="card-body">
                <p class="lt-section-title mb-1">Punch-in by hour <small>when people arrive</small></p>
                @php $maxHour = max(1, max($hourly)); @endphp
                <div class="lt-bars">
                    @foreach($hourly as $h => $count)
                        <div class="b {{ $count ? '' : 'zero' }}" style="height:{{ max(2, $count * 100 / $maxHour) }}%;"
                             data-tip="{{ \Carbon\Carbon::today()->setHour($h)->format('g A') }}: {{ $count }}"></div>
                    @endforeach
                </div>
                <div class="lt-bar-axis">
                    @foreach($hourly as $h => $count)
                        <span>{{ $h % 3 === 0 ? \Carbon\Carbon::today()->setHour($h)->format('ga') : '' }}</span>
                    @endforeach
                </div>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100"><div class="card-body">
                <p class="lt-section-title mb-2">By department <small>punches · <span style="color:#ef4444">off-site</span></small></p>
                @php $maxDept = max(1, $byDepartment->max('punches') ?? 1); @endphp
                @forelse($byDepartment->take(8) as $dept)
                    <div class="lt-hbar">
                        <div class="top"><span class="text-truncate">{{ $dept['name'] }}</span><span class="text-muted">{{ $dept['employees'] }} emp · {{ $dept['punches'] }}</span></div>
                        <div class="track">
                            <div class="fill" style="width:{{ ($dept['punches'] - $dept['offsite']) * 100 / $maxDept }}%"></div>
                            <div class="fill off" style="width:{{ $dept['offsite'] * 100 / $maxDept }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="text-muted small">No data.</div>
                @endforelse
            </div></div>
        </div>
        <div class="col-lg-3">
            <div class="card h-100"><div class="card-body">
                <p class="lt-section-title mb-2">Longest in &rarr; out distance</p>
                @forelse($topTravellers as $t)
                    <div class="d-flex align-items-center gap-2 mb-2 js-lt-focus" data-emp="{{ $t['employee_key'] }}" data-row="{{ $t['id'] }}" style="cursor:pointer;">
                        <img src="{{ $t['image'] }}" class="lt-avatar" style="width:30px;height:30px;" alt="">
                        <div class="flex-grow-1" style="min-width:0;">
                            <div class="small fw-semibold text-truncate">{{ $t['name'] }}</div>
                            <div class="text-muted" style="font-size:11px;">{{ $t['date_label'] }}</div>
                        </div>
                        <span class="lt-badge b-late">{{ $fmtDist($t['travel']) }}</span>
                    </div>
                @empty
                    <div class="text-muted small">Needs both punch-in and punch-out locations.</div>
                @endforelse
            </div></div>
        </div>
        @if($daily->count() > 1)
        <div class="col-12">
            <div class="card"><div class="card-body">
                <p class="lt-section-title mb-1">Daily trend <small>employees tracked per day · <span style="color:#ef4444">with off-site punch</span></small></p>
                @php $maxDay = max(1, $daily->max('employees')); @endphp
                <div class="lt-bars" style="height:120px;">
                    @foreach($daily as $d)
                        <div class="b {{ $d['employees'] ? '' : 'zero' }}" style="height:{{ max(2, $d['employees'] * 100 / $maxDay) }}%; background:linear-gradient(180deg,#3b82f6 0%,#3b82f6 {{ $d['employees'] ? 100 - $d['offsite'] * 100 / $d['employees'] : 100 }}%,#ef4444 {{ $d['employees'] ? 100 - $d['offsite'] * 100 / $d['employees'] : 100 }}%);"
                             data-tip="{{ $d['date'] }}: {{ $d['employees'] }} emp, {{ $d['offsite'] }} off-site"></div>
                    @endforeach
                </div>
                <div class="lt-bar-axis">
                    @foreach($daily as $i => $d)
                        <span>{{ $daily->count() <= 16 || $i % ceil($daily->count() / 12) === 0 ? $d['date'] : '' }}</span>
                    @endforeach
                </div>
            </div></div>
        </div>
        @endif
    </div>

    {{-- Detail table --}}
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <p class="lt-section-title">Punch details <small>{{ $rows->count() }} attendance day(s) · click a row to see it on the map</small></p>
                <input type="search" id="ltTableSearch" class="form-control form-control-sm" style="max-width:260px;" placeholder="Filter table...">
            </div>
            <div class="table-responsive" style="max-height:520px;">
                <table class="table table-sm table-hover lt-table mb-0" id="ltTable">
                    <thead>
                        <tr>
                            <th>#</th><th>Employee</th><th>Date</th>
                            <th>In</th><th>In location</th>
                            <th>Out</th><th>Out location</th>
                            <th class="text-end">Worked</th><th class="text-end">In &rarr; Out</th><th>Site</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $i => $r)
                            <tr class="js-lt-focus" data-emp="{{ $r['employee_key'] }}" data-row="{{ $r['id'] }}">
                                <td>{{ $i + 1 }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="{{ $r['image'] }}" class="lt-avatar" style="width:28px;height:28px;" alt="">
                                        <div>
                                            <div class="fw-semibold">{{ $r['name'] }}</div>
                                            <div class="text-muted" style="font-size:11px;">{{ $r['employee_id'] }} · {{ $r['designation'] }} · {{ $r['department'] }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-nowrap">{{ $r['date_label'] }}</td>
                                <td class="text-nowrap">
                                    {{ $r['in_time'] ?? '—' }}
                                    @if($r['late'])<span class="lt-badge b-late">Late</span>@endif
                                </td>
                                <td class="addr">
                                    @if($r['in'])
                                        {{ $r['in']['address'] ?: number_format($r['in']['lat'], 5) . ', ' . number_format($r['in']['lng'], 5) }}
                                        <a href="https://www.google.com/maps/search/?api=1&query={{ $r['in']['lat'] }},{{ $r['in']['lng'] }}" target="_blank" rel="noopener" onclick="event.stopPropagation()"><i class="fa-solid fa-up-right-from-square"></i></a>
                                        @if($r['in_distance'] !== null)<div>{{ $fmtDist($r['in_distance']) }} from base</div>@endif
                                    @else — @endif
                                </td>
                                <td class="text-nowrap">{{ $r['out_time'] ?? '—' }}</td>
                                <td class="addr">
                                    @if($r['out'])
                                        {{ $r['out']['address'] ?: number_format($r['out']['lat'], 5) . ', ' . number_format($r['out']['lng'], 5) }}
                                        <a href="https://www.google.com/maps/search/?api=1&query={{ $r['out']['lat'] }},{{ $r['out']['lng'] }}" target="_blank" rel="noopener" onclick="event.stopPropagation()"><i class="fa-solid fa-up-right-from-square"></i></a>
                                        @if($r['out_distance'] !== null)<div>{{ $fmtDist($r['out_distance']) }} from base</div>@endif
                                    @elseif($r['in_time'] && !$r['out_time'])
                                        <span class="lt-badge b-miss">Not punched out</span>
                                    @else — @endif
                                </td>
                                <td class="text-end text-nowrap">{{ $r['worked_minute'] ? intdiv($r['worked_minute'], 60) . 'h ' . ($r['worked_minute'] % 60) . 'm' : '—' }}</td>
                                <td class="text-end text-nowrap">{{ $fmtDist($r['travel']) }}</td>
                                <td>
                                    @if($r['in_offsite'] || $r['out_offsite'])
                                        <span class="lt-badge b-off">Off-site{{ $r['in_offsite'] && $r['out_offsite'] ? '' : ($r['in_offsite'] ? ' (in)' : ' (out)') }}</span>
                                    @else
                                        <span class="lt-badge b-on">On-site</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center text-muted py-4">No geo-tagged punches found for the selected filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/leaflet.markercluster.min.js"></script>
<script>
(function () {
    var rows = @json($rows);
    var base = @json($base);
    var radius = {{ (int) $radius }};

    var esc = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };
    var dist = function (m) { return m == null ? '—' : (m >= 1000 ? (m / 1000).toFixed(2) + ' km' : m + ' m'); };
    var initials = function (name) {
        return String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(function (w) { return w[0]; }).join('').toUpperCase();
    };
    var gmaps = function (p) { return 'https://www.google.com/maps/search/?api=1&query=' + p.lat + ',' + p.lng; };

    // ---- Map ---------------------------------------------------------------
    var tiles = {
        street: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }),
        satellite: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: 'Tiles &copy; Esri' })
    };
    var map = L.map('ltMap', { zoomControl: true, layers: [tiles.street] }).setView(base ? [base.lat, base.lng] : [23.8103, 90.4125], base ? 14 : 11);
    L.control.scale({ imperial: false }).addTo(map);

    var makeGroup = function (clustered) {
        return clustered ? L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 40 }) : L.layerGroup();
    };
    var inGroup, outGroup;
    var pathGroup = L.layerGroup().addTo(map);
    var baseGroup = L.layerGroup().addTo(map);
    var markersByRow = {};

    if (base) {
        L.circle([base.lat, base.lng], { radius: radius, color: '#2563eb', weight: 1.5, fillColor: '#3b82f6', fillOpacity: .08, dashArray: '5 5' }).addTo(baseGroup);
        L.marker([base.lat, base.lng], {
            icon: L.divIcon({ className: '', html: '<div class="lt-base-pin"><i class="fa-solid fa-building"></i></div>', iconSize: [34, 34], iconAnchor: [17, 17] }),
            zIndexOffset: -1000
        }).bindTooltip(base.estimated ? 'Estimated base (median of punch-ins)' : 'Office').addTo(baseGroup);
    }

    var pinIcon = function (type, r, offsite) {
        return L.divIcon({
            className: '',
            html: '<div class="lt-pin ' + type + (offsite ? ' off' : '') + '"><span>' + esc(initials(r.name)) + '</span></div>',
            iconSize: [30, 30], iconAnchor: [15, 30], popupAnchor: [0, -28]
        });
    };

    var popupHtml = function (r, focus) {
        var block = function (label, color, time, p, d, off) {
            if (!p) return '';
            return '<div style="border-left:3px solid ' + color + ';padding-left:6px;margin-top:6px;">' +
                '<div><b style="color:' + color + '">' + label + '</b> ' + esc(time || '') + (off ? ' <span class="lt-badge b-off">Off-site</span>' : '') + '</div>' +
                (p.address ? '<div class="text-muted">' + esc(p.address) + '</div>' : '') +
                '<div class="text-muted">' + p.lat.toFixed(5) + ', ' + p.lng.toFixed(5) + (d != null ? ' · ' + dist(d) + ' from base' : '') + '</div>' +
                '<a href="' + gmaps(p) + '" target="_blank" rel="noopener">Open in Google Maps</a></div>';
        };
        return '<div class="lt-pop">' +
            '<div class="hd"><img src="' + esc(r.image) + '" alt=""><div><b>' + esc(r.name) + '</b>' +
            '<span class="text-muted">' + esc(r.employee_id) + ' · ' + esc(r.designation || '') + '</span><br>' +
            '<span class="text-muted">' + esc(r.department || '') + (r.mobile ? ' · <a href="tel:' + esc(r.mobile) + '">' + esc(r.mobile) + '</a>' : '') + '</span></div></div>' +
            '<div class="row2"><span class="k">Date</span><span>' + esc(r.date_label) + '</span></div>' +
            (r.worked_minute ? '<div class="row2"><span class="k">Worked</span><span>' + Math.floor(r.worked_minute / 60) + 'h ' + (r.worked_minute % 60) + 'm</span></div>' : '') +
            (r.travel != null ? '<div class="row2"><span class="k">In → out</span><span>' + dist(r.travel) + '</span></div>' : '') +
            block('Punch-in', '#16a34a', r.in_time, r['in'], r.in_distance, r.in_offsite && focus !== 'out') +
            block('Punch-out', '#dc2626', r.out_time, r.out, r.out_distance, r.out_offsite && focus !== 'in') +
            (r.in_time && !r.out_time ? '<div class="mt-1"><span class="lt-badge b-miss">Not punched out yet</span></div>' : '') +
            '</div>';
    };

    var visibleRows = rows;
    var drawMarkers = function () {
        var clustered = document.getElementById('ltCluster').checked;
        if (inGroup) map.removeLayer(inGroup);
        if (outGroup) map.removeLayer(outGroup);
        pathGroup.clearLayers();
        inGroup = makeGroup(clustered);
        outGroup = makeGroup(clustered);
        markersByRow = {};

        visibleRows.forEach(function (r) {
            markersByRow[r.id] = {};
            if (r['in']) {
                markersByRow[r.id]['in'] = L.marker([r['in'].lat, r['in'].lng], { icon: pinIcon('in', r, r.in_offsite) })
                    .bindPopup(popupHtml(r, 'in')).bindTooltip(r.name + ' · in ' + (r.in_time || '')).addTo(inGroup);
            }
            if (r.out) {
                markersByRow[r.id].out = L.marker([r.out.lat, r.out.lng], { icon: pinIcon('out', r, r.out_offsite) })
                    .bindPopup(popupHtml(r, 'out')).bindTooltip(r.name + ' · out ' + (r.out_time || '')).addTo(outGroup);
            }
            if (r['in'] && r.out) {
                L.polyline([[r['in'].lat, r['in'].lng], [r.out.lat, r.out.lng]], { color: '#7c3aed', weight: 2, opacity: .6, dashArray: '4 6' }).addTo(pathGroup);
            }
        });

        if (document.getElementById('ltLayerIn').checked) inGroup.addTo(map);
        if (document.getElementById('ltLayerOut').checked) outGroup.addTo(map);
    };

    var fitVisible = function () {
        var pts = [];
        visibleRows.forEach(function (r) {
            if (r['in']) pts.push([r['in'].lat, r['in'].lng]);
            if (r.out) pts.push([r.out.lat, r.out.lng]);
        });
        if (pts.length === 1) map.setView(pts[0], 16);
        else if (pts.length) map.fitBounds(pts, { padding: [40, 40], maxZoom: 17 });
    };

    drawMarkers();
    fitVisible();

    var toggle = function (id, getLayer) {
        document.getElementById(id).addEventListener('change', function (e) {
            var layer = getLayer();
            if (e.target.checked) layer.addTo(map); else map.removeLayer(layer);
        });
    };
    toggle('ltLayerIn', function () { return inGroup; });
    toggle('ltLayerOut', function () { return outGroup; });
    toggle('ltLayerPath', function () { return pathGroup; });
    toggle('ltLayerBase', function () { return baseGroup; });
    document.getElementById('ltCluster').addEventListener('change', drawMarkers);
    document.querySelectorAll('input[name="ltTile"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            Object.keys(tiles).forEach(function (k) { map.removeLayer(tiles[k]); });
            tiles[radio.value].addTo(map);
        });
    });

    // ---- Employee list -----------------------------------------------------
    var employees = {};
    rows.forEach(function (r) {
        var e = employees[r.employee_key] || (employees[r.employee_key] = { key: r.employee_key, rows: [], latest: r, offsite: false, missing: false, late: false });
        e.rows.push(r);
        e.latest = r; // rows arrive date-ascending, so the last one is the latest day
        e.offsite = e.offsite || r.in_offsite || r.out_offsite;
        e.missing = e.missing || (!!r.in_time && !r.out_time);
        e.late = e.late || r.late;
    });
    var empList = Object.keys(employees).map(function (k) { return employees[k]; })
        .sort(function (a, b) { return String(a.latest.name).localeCompare(String(b.latest.name)); });

    var activeChip = 'all', activeEmp = null;
    var listEl = document.getElementById('ltEmpList');

    var renderList = function () {
        var q = document.getElementById('ltSearch').value.trim().toLowerCase();
        var shown = empList.filter(function (e) {
            var r = e.latest;
            if (activeChip === 'offsite' && !e.offsite) return false;
            if (activeChip === 'missing' && !e.missing) return false;
            if (activeChip === 'late' && !e.late) return false;
            return !q || [r.name, r.employee_id, r.department, r.designation, r.section].join(' ').toLowerCase().indexOf(q) !== -1;
        });
        document.getElementById('ltEmpCount').textContent = '(' + shown.length + ')';
        listEl.innerHTML = shown.length ? shown.map(function (e) {
            var r = e.latest;
            var badge = e.offsite ? '<span class="lt-badge b-off">Off-site</span>'
                : (e.missing ? '<span class="lt-badge b-miss">No out</span>' : '<span class="lt-badge b-on">On-site</span>');
            return '<div class="lt-emp' + (activeEmp == e.key ? ' active' : '') + '" data-emp="' + e.key + '">' +
                '<img class="lt-avatar" src="' + esc(r.image) + '" alt="">' +
                '<div style="min-width:0;"><div class="nm">' + esc(r.name) + '</div>' +
                '<div class="meta">' + esc(r.employee_id) + ' · ' + esc(r.designation || r.department || '') + '</div>' +
                '<div class="meta">' + (e.rows.length > 1 ? e.rows.length + ' days · last ' : '') + esc(r.date_label) + '</div></div>' +
                '<div class="rt">' + badge + '<div class="meta mt-1">' +
                (r.in_time ? '<span style="color:var(--lt-in)">' + esc(r.in_time) + '</span>' : '') +
                (r.out_time ? ' · <span style="color:var(--lt-out)">' + esc(r.out_time) + '</span>' : '') + '</div></div></div>';
        }).join('') : '<div class="text-center text-muted small py-5">No employees match.</div>';
    };

    var focusEmployee = function (key, rowId) {
        activeEmp = key;
        visibleRows = rows.filter(function (r) { return r.employee_key == key; });
        drawMarkers();
        fitVisible();
        renderList();
        var target = rowId ? rows.find(function (r) { return r.id == rowId; }) : employees[key] && employees[key].latest;
        if (target && markersByRow[target.id]) {
            var m = markersByRow[target.id].out || markersByRow[target.id]['in'];
            if (m) setTimeout(function () { m.openPopup(); }, 350);
        }
        var item = listEl.querySelector('[data-emp="' + key + '"]');
        if (item) item.scrollIntoView({ block: 'nearest' });
    };

    listEl.addEventListener('click', function (e) {
        var item = e.target.closest('.lt-emp');
        if (item) focusEmployee(item.dataset.emp);
    });
    document.querySelectorAll('.js-lt-focus').forEach(function (el) {
        el.addEventListener('click', function () {
            focusEmployee(el.dataset.emp, el.dataset.row);
            document.getElementById('ltMap').scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });
    document.getElementById('ltShowAll').addEventListener('click', function () {
        activeEmp = null;
        visibleRows = rows;
        map.closePopup();
        drawMarkers();
        fitVisible();
        renderList();
    });
    document.getElementById('ltSearch').addEventListener('input', renderList);
    document.querySelectorAll('.lt-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            document.querySelectorAll('.lt-chip').forEach(function (c) { c.classList.remove('active'); });
            chip.classList.add('active');
            activeChip = chip.dataset.filter;
            renderList();
        });
    });
    renderList();

    // ---- Table filter ------------------------------------------------------
    document.getElementById('ltTableSearch').addEventListener('input', function (e) {
        var q = e.target.value.trim().toLowerCase();
        document.querySelectorAll('#ltTable tbody tr.js-lt-focus').forEach(function (tr) {
            tr.style.display = !q || tr.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
        });
    });

    // ---- Quick ranges ------------------------------------------------------
    var form = document.getElementById('ltFilterForm');
    var iso = function (d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
    document.querySelectorAll('.js-lt-quick').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var today = new Date(), from = new Date(), to = new Date();
            if (btn.dataset.range === 'yesterday') { from.setDate(today.getDate() - 1); to.setDate(today.getDate() - 1); }
            if (btn.dataset.range === 'week') { from.setDate(today.getDate() - 6); }
            if (btn.dataset.range === 'month') { from = new Date(today.getFullYear(), today.getMonth(), 1); }
            form.elements['from'].value = iso(from);
            form.elements['to'].value = iso(to);
            form.submit();
        });
    });

    // ---- Print / Excel -----------------------------------------------------
    document.querySelectorAll('.js-lt-export').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var exportForm = document.getElementById('ltExportForm');
            exportForm.innerHTML = '';
            new FormData(form).forEach(function (value, key) {
                var input = document.createElement('input');
                input.type = 'hidden'; input.name = key; input.value = value;
                exportForm.appendChild(input);
            });
            // Excel needs the real render straight away; Print goes through the loader page.
            [['_render', btn.dataset.xlsx], ['xlsx', btn.dataset.xlsx]].forEach(function (pair) {
                var input = document.createElement('input');
                input.type = 'hidden'; input.name = pair[0]; input.value = pair[1];
                exportForm.appendChild(input);
            });
            exportForm.submit();
        });
    });

    // ---- Live tracking: reload every 60s while on (remembered per browser) --
    var liveBtn = document.getElementById('ltLiveBtn');
    if (liveBtn) {
        var key = 'hrLocationLive', timer = null;
        var setLive = function (on) {
            try { on ? localStorage.setItem(key, '1') : localStorage.removeItem(key); } catch (e) {}
            document.getElementById('ltLive').classList.toggle('off', !on);
            document.getElementById('ltLiveText').textContent = on ? 'Live · refresh 60s' : 'Live off';
            liveBtn.innerHTML = on ? '<i class="fa-solid fa-pause"></i> Stop Live' : '<i class="fa-solid fa-satellite-dish"></i> Live Track';
            clearTimeout(timer);
            if (on) timer = setTimeout(function () { window.location.reload(); }, 60000);
        };
        var stored = false;
        try { stored = localStorage.getItem(key) === '1'; } catch (e) {}
        setLive(stored);
        liveBtn.addEventListener('click', function () { setLive(document.getElementById('ltLive').classList.contains('off')); });
    }
})();

$('.select2').select2({ placeholder: 'All', allowClear: true, width: '100%' });
</script>
@endpush
