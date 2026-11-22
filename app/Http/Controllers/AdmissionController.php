<?php

namespace App\Http\Controllers;

use App\Models\Admission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdmissionController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:pending,waitlisted,admitted,rejected',
        ]);

        $allowed = $request->user()->accessibleBranchIds();
        $scopeAccessibleBranches = static function ($query) use ($allowed): void {
            if ($allowed !== null) {
                $query->whereIn('branch_id', $allowed);
            }
        };

        $statusCounts = Admission::query()
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->tap($scopeAccessibleBranches)
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $statusCounts = collect([
            'pending' => 0,
            'waitlisted' => 0,
            'admitted' => 0,
            'rejected' => 0,
        ])->merge($statusCounts);
        $statusCounts->put('total', $statusCounts->sum());

        $admissions = Admission::with(['student', 'branch', 'academicYear'])
            ->tap($scopeAccessibleBranches)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admissions.index', compact('admissions', 'statusCounts'));
    }

    public function update(Request $request, Admission $admission)
    {
        abort_unless($request->user()->canAccessBranch($admission->branch_id), 403);
        $data = $request->validate(['status' => 'required|in:pending,waitlisted,admitted,rejected', 'review_notes' => 'nullable|string|max:4000']);
        $admission->update($data);
        if (in_array($data['status'], ['rejected', 'waitlisted'], true) && $admission->student) {
            $admission->student->update(['status' => $data['status'] === 'rejected' ? 'inactive' : 'active']);
        }

        return back()->with('success', 'Admission decision saved.');
    }

    public function upload(Request $request, Admission $admission)
    {
        abort_unless($request->user()->canAccessBranch($admission->branch_id), 403);
        $data = $request->validate(['document' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240', 'document_name' => 'required|string|max:120']);
        $path = $request->file('document')->store('admission-documents/'.$admission->id, 'local');
        $documents = $admission->documents ?? [];
        $documents[] = ['name' => $data['document_name'], 'file_name' => $request->file('document')
            ->getClientOriginalName(), 'path' => $path, 'uploaded_by' => $request
            ->user()
            ->id, 'uploaded_at' => now()
            ->toISOString()];
        $admission->update(['documents' => $documents]);

        return back()->with('success', 'Admission document uploaded.');
    }

    public function download(Request $request, Admission $admission, int $document)
    {
        abort_unless($request->user()->canAccessBranch($admission->branch_id), 403);
        $item = ($admission->documents ?? [])[$document] ?? abort(404);
        abort_unless(Storage::disk('local')->exists($item['path']), 404);

        return Storage::disk('local')->download($item['path'], $item['file_name']);
    }
}
