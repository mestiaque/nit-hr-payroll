@extends(adminTheme().'layouts.app')
@section('title')
<title>{{ websiteTitle('Import Attendance') }}</title>
@endsection

@push('css')
<style>
    .import-container { max-width: 760px; margin: 30px auto; }
    .upload-area {
        border: 2px dashed #56d2ff; border-radius: 15px; padding: 36px; text-align: center;
        background: #f8fdff; cursor: pointer; transition: all .3s ease; position: relative;
    }
    .upload-area:hover, .upload-area.dragover { background: #eefbff; border-color: #007bff; }
    .upload-icon { font-size: 46px; color: #56d2ff; margin-bottom: 12px; }
    #attendance_file { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
    .file-info { margin-top: 12px; display: none; font-weight: 600; color: #333; }
    .instruction-card { background: #fff; border-radius: 10px; padding: 15px; border-left: 4px solid #56d2ff; margin-bottom: 20px; }
    .btn-import { background: #56d2ff; border: none; color: #fff; padding: 10px 30px; font-weight: 600; border-radius: 25px; }
    .btn-import:hover { background: #33b5e5; color: #fff; }
    .mode-option { border: 1px solid #dee2e6; border-radius: 8px; padding: 10px 12px; cursor: pointer; flex: 1; min-width: 220px; }
    .mode-option input { margin-right: 6px; }
</style>
@endpush

@section('contents')
@include(adminTheme().'alerts')
<div class="flex-grow-1">
    <div class="container-fluid">
        <div class="import-container">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap" style="gap:8px;">
                    <h4 class="card-title mb-0">Import Attendance</h4>
                    <div class="d-flex flex-wrap" style="gap:6px;">
                        <a href="{{ route('hr-center.attendances.import.template') }}" class="btn btn-sm btn-outline-success">
                            <i class="fa fa-file-excel mr-1"></i> Template
                        </a>
                        <a href="{{ route('hr-center.attendances.import.template', ['all' => 1]) }}" class="btn btn-sm btn-outline-success" title="Template pre-filled with every active employee for yesterday">
                            <i class="fa fa-users mr-1"></i> Template (all employees)
                        </a>
                        <a href="{{ route('hr-center.attendances.index') }}" class="btn btn-sm btn-outline-info">
                            <i class="fa fa-calendar-check mr-1"></i> Attendance
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="instruction-card shadow-sm">
                        <h6><i class="fa fa-info-circle mr-1"></i> Instructions:</h6>
                        <small class="text-muted">
                            1. One row per employee per day with columns <strong>Employee ID, Date, In Time, Out Time</strong> (Remarks optional). <br>
                            2. A file exported from the Attendance List can be edited and imported back — extra columns are ignored. <br>
                            3. Status (Present / Late / Early Exit / Punch Missing), OT and working hours are calculated from each employee's shift. <br>
                            4. Locked periods, dates after an employee's exit and future dates are skipped and listed below after import.
                        </small>
                    </div>

                    <form id="uploadForm" action="{{ route('hr-center.attendances.import.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="upload-area" id="dropArea">
                            <input type="file" name="attendance_file" id="attendance_file" accept=".xlsx,.xls,.csv" required>
                            <div class="upload-icon"><i class="fa fa-upload"></i></div>
                            <h5>Drag &amp; Drop your Excel / CSV file here</h5>
                            <p class="text-muted mb-0">or click to browse from computer</p>
                            <div id="fileInfo" class="file-info"><i class="fa fa-file text-primary"></i> <span id="fileName"></span></div>
                        </div>
                        @error('attendance_file')
                            <div class="text-danger small mt-2">{{ $message }}</div>
                        @enderror

                        <label class="font-weight-bold mt-3 mb-2 d-block">If attendance already exists for that day</label>
                        <div class="d-flex flex-wrap" style="gap:10px;">
                            <label class="mode-option mb-0">
                                <input type="radio" name="mode" value="overwrite" {{ old('mode', 'overwrite') === 'overwrite' ? 'checked' : '' }}>
                                <strong>Overwrite</strong><br><small class="text-muted">Replace in/out time with the file's values</small>
                            </label>
                            <label class="mode-option mb-0">
                                <input type="radio" name="mode" value="skip" {{ old('mode') === 'skip' ? 'checked' : '' }}>
                                <strong>Keep existing</strong><br><small class="text-muted">Only add days that have no attendance yet</small>
                            </label>
                        </div>

                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-import shadow-sm" id="submitBtn">
                                <i class="fa fa-upload mr-1"></i> Start Import
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            @include('hr::attendances.partials.import-result')
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
    (function () {
        const dropArea = document.getElementById('dropArea');
        const fileInput = document.getElementById('attendance_file');

        ['dragenter', 'dragover'].forEach(e => dropArea.addEventListener(e, () => dropArea.classList.add('dragover')));
        ['dragleave', 'drop'].forEach(e => dropArea.addEventListener(e, () => dropArea.classList.remove('dragover')));

        fileInput.addEventListener('change', function () {
            if (this.files.length > 0) {
                document.getElementById('fileName').textContent = this.files[0].name;
                document.getElementById('fileInfo').style.display = 'block';
            }
        });

        document.getElementById('uploadForm').addEventListener('submit', function () {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Importing...';
        });
    })();
</script>
@endpush
