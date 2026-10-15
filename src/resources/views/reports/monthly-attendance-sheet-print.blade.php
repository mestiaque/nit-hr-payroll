@extends('printMaster2')

@section('title', 'Employee Attendance Sheet')

@push('css')
<style>
@page { size: A4 landscape; margin: 8mm; }
.mas-wrap { font-size: 9px; }
.report-head { text-align: center; margin-bottom: 10px; }
.report-head h3 { margin: 0 0 2px; font-size: 15px; }
.report-head p { margin: 0; font-size: 11px; }
.sub-title { font-size: 12px; font-weight: 700; margin: 8px 0 4px; }
.mas-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.mas-table th, .mas-table td { border: 1px solid #a6a6a6; padding: 1px 0; font-size: 8px; line-height: 1.3; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: clip; }
.mas-table .t-left { padding: 1px 2px; text-overflow: ellipsis; }
.mas-table thead th { background: #eef1d4; font-weight: 700; }
.mas-table thead th.is-holiday { background: #ffd966; }
.mas-table .t-left { text-align: left; }
.mas-table .col-id { width: 52px; }
.mas-table .col-name { width: 88px; }
.mas-table .col-dept { width: 58px; }
.mas-table .col-desig { width: 62px; }
.mas-table .col-join { width: 44px; }
.mas-table .col-total { width: 27px; font-weight: 700; }
.mas-table .group-row td { background: #dde6f0; font-weight: 700; text-align: left; font-size: 9px; padding: 2px 6px; }
.mas-table td.code-A { color: #c00000; font-weight: 700; }
.mas-legend { margin-top: 8px; display: flex; flex-wrap: wrap; gap: 4px 12px; font-size: 9px; }
.mas-legend span.sw { display: inline-block; min-width: 22px; text-align: center; border: 1px solid #a6a6a6; margin-right: 3px; font-weight: 700; }
@media print { .mas-table th, .mas-table td, .mas-legend span.sw { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
@endpush

@section('contents')
@php
    $days = $sheet['days'];
    $totalCols = count(\ME\Hr\Services\MonthlyAttendanceSheetService::TOTALS);
    $colspan = 5 + count($days) + $totalCols;
    $bg = fn (string $code) => ($color = $sheetService->color($code)) ? 'background:#' . $color . ';' : '';
@endphp

<div class="mas-wrap">
    <div class="report-head">
        @if(!blank(optional(general())->logo()))
            <img src="{{ asset(optional(general())->logo()) }}" alt="Logo" style="max-height:40px;margin-bottom:4px;">
        @endif
        <h3>{{ $meta['company'] }}</h3>
        <p>{{ $meta['address'] }}</p>
    </div>

    <div class="sub-title">{{ $meta['title'] }} &mdash; {{ $meta['period'] }}</div>

    <table class="mas-table">
        <thead>
            <tr>
                <th class="col-id" rowspan="2">Employee ID</th>
                <th class="col-name" rowspan="2">Name</th>
                <th class="col-dept" rowspan="2">Department</th>
                <th class="col-desig" rowspan="2">Designation</th>
                <th class="col-join" rowspan="2">Join Date</th>
                @foreach($days as $day)
                    <th class="day {{ $day['holiday'] ? 'is-holiday' : '' }}">{{ $day['day'] }}</th>
                @endforeach
                @foreach(\ME\Hr\Services\MonthlyAttendanceSheetService::TOTALS_SHORT as $key => $heading)
                    <th class="col-total" rowspan="2" title="{{ \ME\Hr\Services\MonthlyAttendanceSheetService::TOTALS[$key] }}">{{ $heading }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach($days as $day)
                    <th class="day {{ $day['holiday'] ? 'is-holiday' : '' }}">{{ substr($day['dow'], 0, 2) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($groups as $group)
                @if(filled($group['label']))
                    <tr class="group-row"><td colspan="{{ $colspan }}">{{ $group['label'] }}</td></tr>
                @endif
                @foreach($group['employees'] as $employee)
                    @php $data = $sheet['rows'][$employee->id] ?? ['codes' => [], 'totals' => []]; @endphp
                    <tr>
                        <td>{{ $employee->employee_id }}</td>
                        <td class="t-left" title="{{ $employee->name }}">{{ $employee->name }}</td>
                        <td class="t-left" title="{{ optional($employee->department)->name }}">{{ optional($employee->department)->name ?? 'N/A' }}</td>
                        <td class="t-left" title="{{ optional($employee->designation)->name }}">{{ optional($employee->designation)->name ?? 'N/A' }}</td>
                        <td>{{ $employee->joining_date ? $employee->joining_date->format('d-M-y') : '-' }}</td>
                        @foreach($days as $day)
                            @php $code = $data['codes'][$day['date']] ?? ''; @endphp
                            <td class="code-{{ $code }}" style="{{ $bg($code) }}">{{ $code }}</td>
                        @endforeach
                        @foreach(array_keys(\ME\Hr\Services\MonthlyAttendanceSheetService::TOTALS) as $key)
                            <td class="col-total">{{ $data['totals'][$key] ?? 0 }}</td>
                        @endforeach
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="{{ $colspan }}">No employees found.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mas-legend">
        <b>Legend:</b>
        @foreach($legend as $code => $label)
            <div><span class="sw" style="{{ $bg($code) }}">{{ $code }}</span>{{ $label }}</div>
        @endforeach
        <div><span class="sw" style="{{ $bg('') }}">&nbsp;</span>Before joining / after exit / future date</div>
    </div>
</div>
@endsection
