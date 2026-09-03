<?php

namespace ME\Hr\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ME\Hr\Models\HrNotice;

class NoticeController extends Controller
{
    public function index(Request $request)
    {
        $query = HrNotice::latest('published_at');

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $notices = $query->paginate(20)->appends($request->query());

        return view('hr::notices.index', [
            'notices' => $notices,
            'request' => $request,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:5000',
            'published_at' => 'required|date',
            'status' => 'nullable|integer|in:0,1',
        ]);

        $validated['status'] = (int) ($validated['status'] ?? 1);

        HrNotice::create($validated);

        return redirect()->route('hr-center.notices.index')->with('success', 'Notice published successfully.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $notice = HrNotice::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:5000',
            'published_at' => 'required|date',
            'status' => 'nullable|integer|in:0,1',
        ]);

        $validated['status'] = (int) ($validated['status'] ?? 1);

        $notice->update($validated);

        return redirect()->route('hr-center.notices.index')->with('success', 'Notice updated successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        HrNotice::findOrFail($id)->delete();

        return redirect()->route('hr-center.notices.index')->with('success', 'Notice deleted successfully.');
    }
}
