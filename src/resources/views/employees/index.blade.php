@extends('admin.layouts.app')

@section('title')
<title>HR Employees</title>
@endsection

@section('contents')
<div class="flex-grow-1">
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Employees</h4>
            <a href="javascript:void(0)" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#CreateEmployeeModal" title="Create Employee"><i class="fa-solid fa-plus"></i></a>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            @php
                $classificationMap = collect($options['classifications'] ?? [])->pluck('name', 'id');
                $departmentMap = collect($options['departments'] ?? [])->pluck('name', 'id');
                $sectionMap = collect($options['sections'] ?? [])->pluck('name', 'id');
                $subSectionMap = collect($options['subSections'] ?? [])->pluck('name', 'id');
                $designationMap = collect($options['designations'] ?? [])->pluck('name', 'id');
                $shiftMap = collect($options['shifts'] ?? [])->pluck('name', 'id');
                $workingPlaceMap = collect($options['workingPlaces'] ?? [])->pluck('name', 'id');
                $lineMap = collect($options['lines'] ?? [])->mapWithKeys(fn ($row) => [
                    $row->id => trim(($row->name ?? '') . (filled($row->slug ?? null) ? ' - ' . $row->slug : '')),
                ]);
            @endphp

            <form method="get" class="row g-2 mb-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label mb-1">Emp ID</label>
                    <input type="text" name="emp_id" value="{{ $request->emp_id }}" class="form-control form-control-sm" placeholder="Emp ID">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Name</label>
                    <input type="text" name="name_filter" value="{{ $request->name_filter }}" class="form-control form-control-sm" placeholder="Name">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Joining Date</label>
                    <input type="date" name="joining_date" value="{{ $request->joining_date }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Contact</label>
                    <input type="text" name="contact" value="{{ $request->contact }}" class="form-control form-control-sm" placeholder="Mobile">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Classification</label>
                    <select name="classification_id" class="form-control form-control-sm">
                        <option value="">All</option>
                        @foreach($options['classifications'] as $row)
                            <option value="{{ $row->id }}" @selected((string) $request->classification_id === (string) $row->id)>{{ $row->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Department</label>
                    <select name="department_id" class="form-control form-control-sm">
                        <option value="">All</option>
                        @foreach($options['departments'] as $row)
                            <option value="{{ $row->id }}" @selected((string) $request->department_id === (string) $row->id)>{{ $row->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Section</label>
                    <select name="section_id" class="form-control form-control-sm">
                        <option value="">All</option>
                        @foreach($options['sections'] as $row)
                            <option value="{{ $row->id }}" @selected((string) $request->section_id === (string) $row->id)>{{ $row->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Sub Section</label>
                    <select name="sub_section_id" class="form-control form-control-sm">
                        <option value="">All</option>
                        @foreach($options['subSections'] as $row)
                            <option value="{{ $row->id }}" @selected((string) $request->sub_section_id === (string) $row->id)>{{ $row->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Designation</label>
                    <select name="designation_id" class="form-control form-control-sm">
                        <option value="">All</option>
                        @foreach($options['designations'] as $row)
                            <option value="{{ $row->id }}" @selected((string) $request->designation_id === (string) $row->id)>{{ $row->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Shift</label>
                    <select name="shift_id" class="form-control form-control-sm">
                        <option value="">All</option>
                        @foreach($options['shifts'] as $row)
                            <option value="{{ $row->id }}" @selected((string) $request->shift_id === (string) $row->id)>{{ $row->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Working Place</label>
                    <select name="working_place_id" class="form-control form-control-sm">
                        <option value="">All</option>
                        @foreach($options['workingPlaces'] as $row)
                            <option value="{{ $row->id }}" @selected((string) $request->working_place_id === (string) $row->id)>{{ $row->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Line</label>
                    <select name="line_id" class="form-control form-control-sm">
                        <option value="">All</option>
                        @foreach($options['lines'] as $row)
                            <option value="{{ $row->id }}" @selected((string) $request->line_id === (string) $row->id)>{{ trim(($row->name ?? '') . (filled($row->slug ?? null) ? ' - ' . $row->slug : '')) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Weekend</label>
                    <select name="weekend" class="form-control form-control-sm">
                        <option value="">All</option>
                        @foreach(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $day)
                            <option value="{{ $day }}" @selected($request->weekend === $day)>{{ $day }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Status</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="">All</option>
                        <option value="regular" @selected($request->status === 'regular')>Regular</option>
                        <option value="lefty" @selected($request->status === 'lefty')>Lefty</option>
                        <option value="resign" @selected($request->status === 'resign')>Resign</option>
                        <option value="transfer" @selected($request->status === 'transfer')>Transfer</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Is Active</label>
                    <select name="is_active" class="form-control form-control-sm">
                        <option value="">All</option>
                        <option value="active" @selected($request->is_active === 'active')>Active</option>
                        <option value="inactive" @selected($request->is_active === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label mb-1 d-block">&nbsp;</label>
                    <button class="btn btn-secondary btn-sm w-100"><i class="fa-solid fa-filter"></i></button>
                </div>
                <div class="col-md-1">
                    <label class="form-label mb-1 d-block">&nbsp;</label>
                    <a href="{{ route('hr-center.employees.index') }}" class="btn btn-light btn-sm w-100"><i class="fa-solid fa-rotate-left"></i></a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th >Emp ID</th>
                            <th>Name</th>
                            <th>Joining Date</th>
                            <th>Contact</th>
                            <th>Classification</th>
                            <th>Department</th>
                            <th>Section</th>
                            <th>Sub Section</th>
                            <th>Designation</th>
                            <th>Shift</th>
                            <th>Working Place</th>
                            <th>Line</th>
                            <th>Weekend</th>
                            <th>Status</th>
                            <th>Is Active</th>
                            <th style="width: 12rem;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employees as $employee)
                            @php
                                $other = is_array($employee->other_information) ? $employee->other_information : json_decode($employee->other_information, true);
                                $profile = is_array($other) ? data_get($other, 'profile', []) : [];
                                $weekend = $employee->weekend ?? data_get($profile, 'weekend');
                                $employmentStatus = (string) ($employee->employment_status ?? 'regular');
                                if ($employmentStatus === '') {
                                    $employmentStatus = 'regular';
                                }
                                if ($employmentStatus === 'left') {
                                    $employmentStatus = 'lefty';
                                }
                                if ($employmentStatus === 'resigned') {
                                    $employmentStatus = 'resign';
                                }
                                $isActive = (string) ($employee->status ?? '') === '1' || (string) ($employee->status ?? '') === 'active';
                            @endphp
                            <tr @if($employmentStatus === 'resign') style="background-color:#fde8e8;" @endif>

                                <td>{{ $employee->employee_id }}</td>
                                <td>{{ $employee->name }}</td>
                                <td>{{ optional($employee->joining_date)->format('d-M-Y') }}</td>
                                <td>{{ $employee->mobile }}</td>
                                <td>{{ $classificationMap->get($employee->employee_type) }}</td>
                                <td>{{ $departmentMap->get($employee->department_id) }}</td>
                                <td>{{ $sectionMap->get($employee->section_id) }}</td>
                                <td>{{ $subSectionMap->get($employee->sub_section_id ?? data_get($profile, 'sub_section_id')) }}</td>
                                <td>{{ $designationMap->get($employee->designation_id) }}</td>
                                <td>{{ $shiftMap->get($employee->shift_id) }}</td>
                                <td>{{ $workingPlaceMap->get($employee->working_place_id ?? data_get($profile, 'working_place_id')) }}</td>
                                <td>{{ $lineMap->get($employee->line_number) }}</td>
                                <td>{{ $weekend }}</td>
                                <td>{{ ucfirst($employmentStatus) }}</td>
                                <td>{{ $isActive ? 'Active' : 'Inactive' }}</td>
                                <td >
                                    <a href="{{ route('hr-center.employees.show', $employee->id) }}" class="btn-custom" title="View Full Profile"><i class="fa-solid fa-eye"></i></a>
                                    <a href="javascript:void(0)" class="btn-custom" data-toggle="modal" data-target="#EditEmployeeModal_{{ $employee->id }}" title="Edit Profile"><i class="fa-solid fa-pen"></i></a>
                                    <a href="javascript:void(0)" class="btn-custom" data-toggle="modal" data-target="#BasicInfoModal_{{ $employee->id }}" title="Basic Info"><i class="fa-solid fa-circle-info"></i></a>
                                    <a href="javascript:void(0)" class="btn-custom" data-toggle="modal" data-target="#SalaryModal_{{ $employee->id }}" title="Salary Info"><i class="fa-solid fa-money-bill-wave"></i></a>
                                    <a href="javascript:void(0)" class="btn-custom" data-toggle="modal" data-target="#AddressModal_{{ $employee->id }}" title="Address"><i class="fa-solid fa-location-dot"></i></a>
                                    <a href="javascript:void(0)" class="btn-custom" data-toggle="modal" data-target="#NomineeModal_{{ $employee->id }}" title="Nominee Information"><i class="fa-solid fa-people-arrows"></i></a>
                                    <a href="javascript:void(0)" class="btn-custom" data-toggle="modal" data-target="#AgeModal_{{ $employee->id }}" title="Age Verification"><i class="fa-solid fa-id-card"></i></a>
                                    <a href="javascript:void(0)" class="btn-custom" data-toggle="modal" data-target="#ResignModal_{{ $employee->id }}" title="Lefty / Resign"><i class="fa-solid fa-right-from-bracket"></i></a>
                                    <a href="javascript:void(0)" class="btn-custom" data-toggle="modal" data-target="#FinalSettlementModal_{{ $employee->id }}" title="Final Settlement Info"><i class="fa-solid fa-file-invoice"></i></a>
                                    <a href="{{ route('hr-center.employees.increments.page', $employee->id) }}" class="btn-custom" title="Salary Increment Info"><i class="fa-solid fa-arrow-trend-up"></i></a>
                                    <a href="{{ route('hr-center.employees.earnings.page', $employee->id) }}" class="btn-custom" title="Earnings &amp; Deductions"><i class="fa-solid fa-scale-balanced"></i></a>
                                    <a href="{{ route('hr-center.employees.leaves.page', $employee->id) }}" class="btn-custom" title="Leave Table"><i class="fa-solid fa-calendar-days"></i></a>
                                    <a href="{{ route('hr-center.employees.documents.page', $employee->id) }}" class="btn-custom" title="Documents"><i class="fa-solid fa-folder-open"></i></a>
                                    <a href="javascript:void(0)" class="btn-custom" data-toggle="modal" data-target="#PortalAccessModal_{{ $employee->id }}" title="Employee Portal Login Access"><i class="fa-solid fa-key"></i></a>
                                    <form method="post" action="{{ route('hr-center.employees.destroy', $employee->id) }}" style="display:inline" class="no-loader">@csrf @method('delete')<button type="button" class="btn-custom danger" title="Delete Employee" style="" onclick="if(confirm('Are you sure you want to delete this employee?')){if(typeof XLoader!=='undefined')XLoader.show();this.closest('form').submit();}"><i class="fa-solid fa-trash"></i></button></form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="16" class="text-center">No employee found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $employees->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

<div class="modal fade" id="CreateEmployeeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <form method="post" action="{{ route('hr-center.employees.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Create Employee</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    @include('hr::employees.partials.profile-fields', ['employee' => $newEmployee, 'options' => $options])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

@foreach($employees as $employee)
<div class="modal fade" id="EditEmployeeModal_{{ $employee->id }}" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <form method="post" action="{{ route('hr-center.employees.profile.update', $employee->id) }}" enctype="multipart/form-data">
                @csrf
                @method('put')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Employee</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">@include('hr::employees.partials.profile-fields', ['employee' => $employee, 'options' => $options])</div>
                <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary btn-sm">Update</button></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="SalaryModal_{{ $employee->id }}" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document"><div class="modal-content"><form method="post" action="{{ route('hr-center.employees.salary.update', $employee->id) }}">@csrf @method('put')
        <div class="modal-header"><h5 class="modal-title">Salary Info</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body">@include('hr::employees.partials.salary-fields', ['employee' => $employee])</div>
        <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary btn-sm">Save</button></div>
    </form></div></div>
</div>

<div class="modal fade" id="AddressModal_{{ $employee->id }}" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document"><div class="modal-content"><form method="post" action="{{ route('hr-center.employees.address.update', $employee->id) }}">@csrf @method('put')
        <div class="modal-header"><h5 class="modal-title">Address</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body">@include('hr::employees.partials.address-fields', ['employee' => $employee, 'options' => $options])</div>
        <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary btn-sm">Save</button></div>
    </form></div></div>
</div>

<div class="modal fade" id="NomineeModal_{{ $employee->id }}" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document"><div class="modal-content"><form method="post" action="{{ route('hr-center.employees.nominee.update', $employee->id) }}" enctype="multipart/form-data">@csrf @method('put')
        <div class="modal-header"><h5 class="modal-title">Nominee Information</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body">@include('hr::employees.partials.nominee-fields', ['employee' => $employee, 'options' => $options])</div>
        <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary btn-sm">Save</button></div>
    </form></div></div>
</div>

<div class="modal fade" id="AgeModal_{{ $employee->id }}" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document"><div class="modal-content"><form method="post" action="{{ route('hr-center.employees.age.update', $employee->id) }}">@csrf @method('put')
        <div class="modal-header"><h5 class="modal-title">Age Verification Information</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body">@include('hr::employees.partials.age-fields', ['employee' => $employee])</div>
        <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary btn-sm">Save</button></div>
    </form></div></div>
</div>

<div class="modal fade" id="ResignModal_{{ $employee->id }}" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document"><div class="modal-content"><form method="post" action="{{ route('hr-center.employees.resign.update', $employee->id) }}">@csrf @method('put')
        <div class="modal-header"><h5 class="modal-title">Lefty & Resign Information</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body">@include('hr::employees.partials.resign-fields', ['employee' => $employee])</div>
        <div class="modal-footer">
            @if($employee->employment_status === 'resign')
                <a href="{{ route('hr-center.employees.resign.print.show', $employee->id) }}" target="_blank" class="btn btn-outline-primary btn-sm mr-auto"><i class="fa-solid fa-print"></i> Print Resign</a>
            @endif
            <button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary btn-sm">Save</button>
        </div>
    </form></div></div>
</div>

<div class="modal fade" id="FinalSettlementModal_{{ $employee->id }}" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document"><div class="modal-content"><form method="post" action="{{ route('hr-center.employees.final-settlement.update', $employee->id) }}">@csrf @method('put')
        <div class="modal-header"><h5 class="modal-title">Final Settlement Information</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body">@include('hr::employees.partials.final-settlement-fields', ['employee' => $employee])</div>
        <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary btn-sm">Save</button></div>
    </form></div></div>
</div>

<div class="modal fade" id="BasicInfoModal_{{ $employee->id }}" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document"><div class="modal-content"><form method="post" action="{{ route('hr-center.employees.basic-info.update', $employee->id) }}">@csrf @method('put')
        <div class="modal-header"><h5 class="modal-title">Basic Info — {{ $employee->name }}</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body">@include('hr::employees.partials.basic-info-fields', ['employee' => $employee, 'basicInfoOptions' => $basicInfoOptions ?? [], 'options' => $options ?? []])</div>
        <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary btn-sm">Save</button></div>
    </form></div></div>
</div>

<div class="modal fade" id="PortalAccessModal_{{ $employee->id }}" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <form method="post" action="{{ route('hr-center.employees.portal-access.save', $employee->id) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Employee Portal Login — {{ $employee->name }}</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-2">
                        <label class="form-label">Employee ID</label>
                        <input type="text" class="form-control form-control-sm" value="{{ $employee->employee_id }}" readonly>
                    </div>
                    <div class="form-group mb-2">
                        <label class="form-label">Password</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="password" id="portal_pw_{{ $employee->id }}" class="form-control form-control-sm"
                                   value="{{ optional($employee->portalLogin)->password_show }}"
                                   placeholder="Click Generate or type a password" required minlength="6">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" onclick="generatePortalPassword('portal_pw_{{ $employee->id }}')" title="Generate random password">
                                    <i class="fa-solid fa-rotate"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Login URL</label>
                        <input type="text" id="portal_url_{{ $employee->id }}" class="form-control form-control-sm" value="{{ url('/employee-portal/login') }}" readonly>
                    </div>
                    @if($employee->portalLogin)
                        <div class="mt-2">
                            <span class="badge badge-{{ $employee->portalLogin->is_active ? 'success' : 'secondary' }}">
                                {{ $employee->portalLogin->is_active ? 'Active' : 'Disabled' }}
                            </span>
                        </div>
                    @endif
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyPortalAccess(this, 'portal_pw_{{ $employee->id }}', 'portal_url_{{ $employee->id }}', '{{ $employee->employee_id }}')">
                        <i class="fa-solid fa-copy me-1"></i> Copy
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endsection
@push('css')
<style>
    .btn-custom{
        padding: 3px 5px !important;
    }
    .copy-btn-flash {
        transition: background-color .15s ease, color .15s ease, transform .15s ease;
        background-color: #198754 !important;
        border-color: #198754 !important;
        color: #fff !important;
        transform: scale(1.06);
    }
</style>
@endpush
@push('js')
<script>
function generatePortalPassword(pwFieldId) {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    var pw = '';
    for (var i = 0; i < 10; i++) {
        pw += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById(pwFieldId).value = pw;
}

function copyPortalAccess(btn, pwFieldId, urlFieldId, employeeId) {
    var pw = document.getElementById(pwFieldId).value;
    var url = document.getElementById(urlFieldId).value;
    var text = "id: " + employeeId + "\npassword: " + pw + "\nlogin: " + url;

    navigator.clipboard.writeText(text).then(function () {
        flashCopyButton(btn);
    });
}

function flashCopyButton(btn) {
    if (!btn || btn.dataset.copyBusy === '1') return;
    btn.dataset.copyBusy = '1';

    var originalHtml = btn.innerHTML;
    btn.classList.add('copy-btn-flash');
    btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Copied!';

    setTimeout(function () {
        btn.classList.remove('copy-btn-flash');
        btn.innerHTML = originalHtml;
        btn.dataset.copyBusy = '0';
    }, 1200);
}
</script>
@endpush
