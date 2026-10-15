@extends('admin.layouts.app')

@section('title')
<title>Import Employees</title>
@endsection

@section('contents')
<div class="flex-grow-1 p-4">
    <div class="alert alert-info small mb-3">
        Download the template (or use <b>Export Excel</b> from the employee list), fill or edit the <b>Employees</b> sheet and upload it here.
        An existing Employee ID is <b>updated</b>, a new one is <b>created</b>. Blank cells never erase saved data.
        The preview shows the main columns only; every column in the file is checked and saved.
    </div>
    @include('hr::partials.excel-import-page', [
        'title' => 'Import Employees from Excel',
        'backUrl' => route('hr-center.employees.index'),
        'templateUrl' => route('hr-center.employees.import.template'),
        'previewUrl' => route('hr-center.employees.import.preview'),
        'saveUrl' => route('hr-center.employees.import.save'),
        'recheckUrl' => route('hr-center.employees.import.recheck'),
        'columns' => [
            ['key' => 'employee_id', 'label' => 'Employee ID'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'join_date', 'label' => 'Joining Date'],
            ['key' => 'department', 'label' => 'Department'],
            ['key' => 'section', 'label' => 'Section'],
            ['key' => 'designation', 'label' => 'Designation'],
            ['key' => 'shift', 'label' => 'Shift'],
            ['key' => 'personal_contact', 'label' => 'Mobile'],
            ['key' => 'gross_salary', 'label' => 'Gross Salary', 'align' => 'right'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'action', 'label' => 'Action'],
        ],
    ])
</div>
@endsection
