@extends('printMaster2')

@section('title', 'Employee Attendance Sheet')

@push('css')
<style>
@page { size: A4 landscape; margin: 6mm; }
.mas-wrap { font-family: Arial, Helvetica, sans-serif; color: #222; }
.mas-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; gap: 10px; }
.mas-top .mas-title { font-size: 12px; }
.mas-top .mas-days { font-size: 11px; color: #555; }
.mas-legend { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
.mas-legend span { font-size: 9px; font-weight: 600; padding: 3px 10px; border-radius: 3px; }
.lg-P { background: #d4edda; color: #28a745; }
.lg-A { background: #f8d7da; color: #dc3545; }
.lg-LT { background: #fff3cd; color: #e0a800; }
.lg-LV { background: #e7d6f7; color: #8e44ad; }
.lg-H { background: #d1ecf1; color: #17a2b8; }
.lg-I { background: #d6d6d6; color: #333; }

.mas-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.mas-table th, .mas-table td { border: 1px solid #dcdcdc; text-align: center; white-space: nowrap; overflow: hidden; }
.mas-table thead th { background: #1f3f99 !important; color: #fff !important; font-weight: 700; border-color: #3553ad !important; }
.mas-table thead .d-num { font-size: 8px; line-height: 1.1; padding: 3px 0 1px; }
.mas-table thead .d-num small { display: block; font-size: 6px; font-weight: 400; }
.mas-table thead .d-dow { font-size: 7px; padding: 1px 0; }
.mas-table thead .h-emp { text-align: left; padding: 4px; font-size: 11px; vertical-align: bottom; width: 210px; }
.mas-table thead .h-total { font-size: 10px; width: 24px; }
.mas-table td { height: 34px; font-size: 7px; font-weight: 600; }
.mas-table td.emp { text-align: left; padding: 3px 4px; }
.emp-box { display: flex; align-items: center; gap: 6px; }
.emp-box img { width: 22px; height: 22px; border-radius: 50%; object-fit: cover; flex: 0 0 22px; }
.emp-box b { display: block; font-size: 9px; overflow: hidden; text-overflow: ellipsis; }
.emp-box small { display: block; font-size: 7px; font-weight: 400; color: #777; margin-top: 2px; }

.c-P { background: #d4edda; color: #28a745; }
.c-A { background: #f8d7da; color: #dc3545; }
.c-LT { background: #fff3cd; color: #e0a800; }
.c-LV { background: #e7d6f7; color: #8e44ad; }
.c-H { background: #fff; color: #17a2b8; }
.c-I { background: #d6d6d6; color: #333; }
.c-X { background: #f2f2f2; }

.t-P { color: #28a745; } .t-A { color: #dc3545; } .t-LT { color: #e0a800; }
.t-LV { color: #8e44ad; } .t-H { color: #17a2b8; } .t-I { color: #222; }
.mas-table td.tot { font-size: 9px; font-weight: 700; background: #fff; }
.mas-table .group-row td { background: #eef2fb; font-weight: 700; text-align: left; font-size: 9px; padding: 3px 6px; height: auto; color: #1f3f99; }
@media print { .mas-table th, .mas-table td, .mas-legend span { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
@endpush

@section('contents')
@php
    $days = $sheet['days'];
    $first = $days ? \Illuminate\Support\Carbon::parse($days[0]['date']) : null;
    $last = $days ? \Illuminate\Support\Carbon::parse(end($days)['date']) : null;
    $totalKeys = ['P' => 'P', 'A' => 'A', 'LT' => 'LT', 'LV' => 'LV', 'H' => 'H', 'I' => 'I'];
    $colspan = 1 + count($days) + count($totalKeys);

    // Sheet codes -> the six display categories of this layout.
    $display = function (string $code): array {
        return match (true) {
            $code === '' => ['X', ''],
            in_array($code, ['P', 'EO'], true) => ['P', 'P'],
            $code === 'L' => ['LT', 'L'],
            $code === 'A' => ['A', 'A'],
            in_array($code, ['W', 'H', 'FH'], true) => ['H', 'H'],
            $code === 'PM' => ['I', 'I'],
            default => ['LV', 'L'],   // any leave type
        };
    };
@endphp

<div class="mas-wrap">
    <div class="mas-top">
        <div class="mas-title">Attendance: {{ $first?->format('d M Y') }} to {{ $last?->format('d M Y') }}</div>
        <div class="mas-legend">
            <span class="lg-P">P = Present</span>
            <span class="lg-A">A = Absent</span>
            <span class="lg-LT">L = Late</span>
            <span class="lg-LV">L = Leave</span>
            <span class="lg-H">H = Holiday/Weekly Off</span>
            <span class="lg-I">I = Incomplete</span>
        </div>
        <div class="mas-days">Total Days: {{ count($days) }}</div>
    </div>

    <table class="mas-table">
        <thead>
            <tr>
                <th class="h-emp" rowspan="2">Employee</th>
                @foreach($days as $day)
                    <th class="d-num">{{ $day['day'] }}<small>{{ \Illuminate\Support\Carbon::parse($day['date'])->format('M') }}</small></th>
                @endforeach
                @foreach($totalKeys as $label)
                    <th class="h-total" rowspan="2">{{ $label }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach($days as $day)
                    <th class="d-dow">{{ substr($day['dow'], 0, 2) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($groups as $group)
                @if(filled($group['label']))
                    <tr class="group-row"><td colspan="{{ $colspan }}">{{ $group['label'] }}</td></tr>
                @endif
                @foreach($group['employees'] as $employee)
                    @php
                        $codes = $sheet['rows'][$employee->id]['codes'] ?? [];
                        $counts = array_fill_keys(array_keys($totalKeys), 0);
                    @endphp
                    <tr>
                        <td class="emp">
                            <div class="emp-box">
                                <img src="{{ asset($employee->image('sm')) }}" alt="">
                                <div style="min-width:0;">
                                    <b title="{{ $employee->name }}">{{ $employee->name }}</b>
                                    <small>{{ $employee->employee_id }}</small>
                                </div>
                            </div>
                        </td>
                        @foreach($days as $day)
                            @php
                                [$cat, $letter] = $display($codes[$day['date']] ?? '');
                                if (isset($counts[$cat])) { $counts[$cat]++; }
                            @endphp
                            <td class="c-{{ $cat }}">{{ $letter }}</td>
                        @endforeach
                        @foreach($counts as $key => $count)
                            <td class="tot t-{{ $key }}">{{ $count }}</td>
                        @endforeach
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="{{ $colspan }}">No employees found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
