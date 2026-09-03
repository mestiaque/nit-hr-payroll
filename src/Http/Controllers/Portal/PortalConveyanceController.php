<?php

namespace ME\Hr\Http\Controllers\Portal;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use ME\Hr\Models\HrConveyanceRequest;

class PortalConveyanceController extends PortalController
{
    public function index()
    {
        $employee = $this->employee();

        $requests = HrConveyanceRequest::where('employee_id', $employee->id)
            ->latest('id')
            ->limit(50)
            ->get();

        return view('hr::portal.conveyance.index', [
            'employee' => $employee,
            'requests' => $requests,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_location' => 'required|string|max:191',
            'to_location' => 'required|string|max:191',
            'travel_by' => 'required|string|max:50',
            'amount' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $employee = $this->employee();

        $conveyance = HrConveyanceRequest::create([
            'request_no' => $this->nextRequestNo(),
            'employee_id' => $employee->id,
            'from_location' => $validated['from_location'],
            'to_location' => $validated['to_location'],
            'travel_by' => $validated['travel_by'],
            'amount' => $validated['amount'],
            'reason' => $validated['reason'] ?? null,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        if ($request->hasFile('attachment') && class_exists(\App\Models\File::class)) {
            $file = $request->file('attachment');
            $path = $file->store("hr/conveyance/{$employee->id}", 'public');

            \App\Models\File::create([
                'fileable_type' => HrConveyanceRequest::class,
                'fileable_id' => $conveyance->id,
                'use_case' => 'attachment',
                'file_name' => (string) \Illuminate\Support\Str::uuid(),
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_full_path' => asset('storage/' . $path),
                'disk' => 'public',
                'extension' => strtolower($file->getClientOriginalExtension()),
                'size' => $file->getSize(),
                'file_type' => $file->getMimeType(),
                'source_table' => 'hr_conveyance_requests',
                'source_id' => $conveyance->id,
            ]);
        }

        if (class_exists(\App\Services\ApprovalService::class)) {
            app(\App\Services\ApprovalService::class)->request([
                'module' => 'hr.conveyance_request',
                'approvable' => $conveyance,
                'title' => "Conveyance Request - {$employee->name}",
                'description' => "{$employee->name} ({$employee->employee_id}) requested {$validated['amount']} for travel from {$validated['from_location']} to {$validated['to_location']}.",
                'route_name' => 'hr-center.conveyance-requests.index',
            ]);
        }

        return redirect()->route('employee-portal.conveyance.index')->with('success', 'Conveyance request submitted for approval.');
    }

    private function nextRequestNo(): string
    {
        $year = now()->year;
        $count = HrConveyanceRequest::whereYear('created_at', $year)->count() + 1;

        return sprintf('CNV-%d-%05d', $year, $count);
    }
}
