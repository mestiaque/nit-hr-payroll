@php $importResult = session('import_result'); @endphp
@if($importResult)
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0"><i class="fa fa-clipboard-check mr-1 text-info"></i> {{ $importResult['title'] }}</h6>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap" style="gap:10px;">
            @foreach($importResult['stats'] as $label => $count)
                <div class="border rounded px-3 py-2 text-center" style="min-width:120px;">
                    <div style="font-size:1.3rem;font-weight:700;">{{ number_format($count) }}</div>
                    <small class="text-muted">{{ $label }}</small>
                </div>
            @endforeach
        </div>

        @if(!empty($importResult['problems']))
            <h6 class="mt-4 mb-2">Rows that need attention</h6>
            <div class="table-responsive" style="max-height:360px;overflow-y:auto;">
                <table class="table table-sm table-bordered mb-0" style="font-size:.85rem;">
                    <thead class="thead-light">
                        <tr><th style="width:80px;">Row</th><th style="width:140px;">Employee ID</th><th>Reason</th></tr>
                    </thead>
                    <tbody>
                        @foreach($importResult['problems'] as [$line, $employeeId, $reason])
                            <tr><td>{{ $line }}</td><td>{{ $employeeId }}</td><td>{{ $reason }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(($importResult['more'] ?? 0) > 0)
                <small class="text-muted d-block mt-2">…and {{ number_format($importResult['more']) }} more row(s) not shown.</small>
            @endif
        @endif
    </div>
</div>
@endif
