<?php

namespace ME\Hr\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ME\Hr\Models\HrConveyanceRequest;
use ME\Hr\Models\HrEmployeeOtherTransaction;

class ConveyanceRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = HrConveyanceRequest::with(['employee.department'])->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('request_no', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($eq) use ($search) {
                        $eq->where('name', 'like', "%{$search}%")
                            ->orWhere('employee_id', 'like', "%{$search}%");
                    });
            });
        }

        $requests = $query->paginate(20)->appends($request->query());

        return view('hr::conveyance-requests.index', [
            'requests' => $requests,
            'request' => $request,
        ]);
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:cash,salary',
            'admin_remark' => 'nullable|string|max:1000',
        ]);

        $conveyance = HrConveyanceRequest::findOrFail($id);

        $this->syncCentralApproval($conveyance, 'approved', $validated['admin_remark'] ?? null);

        $conveyance->status = 'approved';
        $conveyance->admin_remark = $validated['admin_remark'] ?? null;
        $conveyance->approved_by = auth()->id();
        $conveyance->approved_at = now();

        if ($validated['payment_method'] === 'cash') {
            $conveyance->payment_status = 'paid_cash';
            $conveyance->paid_at = now();
        } else {
            HrEmployeeOtherTransaction::create([
                'employee_id' => $conveyance->employee_id,
                'txn_date' => now()->format('Y-m-d'),
                'earnings' => $conveyance->amount,
                'remarks' => "Conveyance {$conveyance->request_no}",
            ]);
            $conveyance->payment_status = 'added_to_salary';
        }

        $conveyance->save();

        return redirect()->route('hr-center.conveyance-requests.index')->with('success', 'Conveyance request approved.');
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'admin_remark' => 'nullable|string|max:1000',
        ]);

        $conveyance = HrConveyanceRequest::findOrFail($id);

        $this->syncCentralApproval($conveyance, 'rejected', $validated['admin_remark'] ?? null);

        $conveyance->status = 'rejected';
        $conveyance->admin_remark = $validated['admin_remark'] ?? null;
        $conveyance->save();

        return redirect()->route('hr-center.conveyance-requests.index')->with('success', 'Conveyance request rejected.');
    }

    /**
     * Approving/rejecting from this screen must also close out the matching
     * row in the host app's central approval inbox, so both stay in sync
     * regardless of which screen HR actually used.
     */
    private function syncCentralApproval(HrConveyanceRequest $conveyance, string $status, ?string $remarks): void
    {
        if (!class_exists(\App\Models\Approval::class) || !class_exists(\App\Services\ApprovalService::class)) {
            return;
        }

        $approval = \App\Models\Approval::where('approvable_type', HrConveyanceRequest::class)
            ->where('approvable_id', $conveyance->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if (!$approval) {
            return;
        }

        $service = app(\App\Services\ApprovalService::class);
        $status === 'approved' ? $service->approve($approval, remarks: $remarks) : $service->reject($approval, remarks: $remarks);
    }
}
