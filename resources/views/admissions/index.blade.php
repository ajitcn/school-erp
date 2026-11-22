@extends('layouts.app')

@section('content')
    <x-admin.page-header
        title="Admissions"
        description="Review applications, supporting documents, and admission decisions."
    />

    <section class="admission-summary-grid" aria-label="Admission status summary">
        <x-admin.stat-card
            label="Total applications"
            :value="number_format($statusCounts['total'])"
            detail="Across accessible branches"
            tone="primary"
            icon="fa-clipboard-list"
        />
        <x-admin.stat-card
            label="Pending review"
            :value="number_format($statusCounts['pending'])"
            detail="Awaiting a decision"
            tone="warning"
            icon="fa-hourglass-half"
        />
        <x-admin.stat-card
            label="Waitlisted"
            :value="number_format($statusCounts['waitlisted'])"
            detail="Follow up when places open"
            tone="info"
            icon="fa-list-check"
        />
        <x-admin.stat-card
            label="Admitted"
            :value="number_format($statusCounts['admitted'])"
            detail="Accepted applications"
            tone="success"
            icon="fa-circle-check"
        />
    </section>

    <div class="admin-card mb-4 p-3 admission-filter-card">
        <form method="get" class="form-row align-items-end js-filter-form" aria-label="Admission filters">
            <div class="form-group col-md-4 mb-2">
                <label for="admission-status">Decision status</label>
                <select class="form-control" id="admission-status" name="status">
                    <option value="">All statuses</option>
                    @foreach (['pending', 'waitlisted', 'admitted', 'rejected'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 mb-2">
                <button class="btn btn-primary" type="submit">Apply filter</button>
                <a class="btn btn-outline-secondary" href="{{ route('admissions.index') }}">Clear</a>
            </div>
        </form>
    </div>

    <section class="admin-card admission-list-card" aria-labelledby="admission-list-title">
        <div class="admission-list-heading">
            <div>
                <h3 id="admission-list-title">Applications</h3>
                <p>
                    Showing {{ $admissions->firstItem() ?? 0 }}–{{ $admissions->lastItem() ?? 0 }}
                    of {{ number_format($admissions->total()) }} applications
                </p>
            </div>
        </div>

        <x-admin.table class="admission-table">
            <thead>
                <tr>
                    <th scope="col">Applicant</th>
                    <th scope="col">Branch / year</th>
                    <th scope="col">Applied</th>
                    <th scope="col">Status</th>
                    <th scope="col">Documents</th>
                    <th scope="col">Review decision</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($admissions as $admission)
                    @php
                        $statusTone = [
                            'pending' => 'warning',
                            'waitlisted' => 'info',
                            'admitted' => 'success',
                            'rejected' => 'danger',
                        ][$admission->status] ?? 'secondary';
                    @endphp
                    <tr>
                        <td>
                            <div class="admission-applicant-name">
                                {{ trim(($admission->student?->first_name ?? '') . ' ' . ($admission->student?->last_name ?? '')) ?: 'Student record unavailable' }}
                            </div>
                            <span class="admission-reference">{{ $admission->admission_number }}</span>
                        </td>
                        <td>
                            <span class="admission-cell-primary">{{ $admission->branch?->name ?? '—' }}</span>
                            <span class="admission-cell-secondary">{{ $admission->academicYear?->name ?? 'Academic year not set' }}</span>
                        </td>
                        <td>
                            <span class="admission-cell-primary">
                                {{ $admission->admission_date?->format('d M Y') ?? '—' }}
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-{{ $statusTone }} admission-status-badge">
                                {{ ucfirst($admission->status) }}
                            </span>
                        </td>
                        <td>
                            <div class="admission-documents">
                                @forelse ($admission->documents ?? [] as $index => $document)
                                    <a
                                        class="admission-document-link"
                                        href="{{ route('admissions.documents.download', [$admission, $index]) }}"
                                        title="Download {{ $document['file_name'] }}"
                                    >
                                        <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
                                        <span>{{ $document['name'] }}</span>
                                    </a>
                                @empty
                                    <span class="admission-no-documents">No documents yet</span>
                                @endforelse
                            </div>

                            <details class="admission-upload-details">
                                <summary><i class="fa-solid fa-plus" aria-hidden="true"></i> Add document</summary>
                                <form
                                    class="admission-upload-form"
                                    method="post"
                                    action="{{ route('admissions.documents.store', $admission) }}"
                                    enctype="multipart/form-data"
                                >
                                    @csrf
                                    <label class="sr-only" for="document-name-{{ $admission->id }}">Document name</label>
                                    <input
                                        class="form-control form-control-sm"
                                        id="document-name-{{ $admission->id }}"
                                        name="document_name"
                                        placeholder="Document name"
                                        maxlength="120"
                                        required
                                    >
                                    <label class="sr-only" for="document-file-{{ $admission->id }}">Choose document</label>
                                    <input
                                        class="form-control-file"
                                        id="document-file-{{ $admission->id }}"
                                        type="file"
                                        name="document"
                                        accept=".pdf,.jpg,.jpeg,.png,.webp"
                                        required
                                    >
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Upload document</button>
                                </form>
                            </details>
                        </td>
                        <td>
                            <form class="admission-review-form" method="post" action="{{ route('admissions.update', $admission) }}">
                                @csrf
                                @method('put')
                                <label class="sr-only" for="decision-{{ $admission->id }}">Decision for {{ $admission->admission_number }}</label>
                                <select class="form-control form-control-sm" id="decision-{{ $admission->id }}" name="status">
                                    @foreach (['pending', 'waitlisted', 'admitted', 'rejected'] as $status)
                                        <option value="{{ $status }}" @selected($admission->status === $status)>
                                            {{ ucfirst($status) }}
                                        </option>
                                    @endforeach
                                </select>
                                <label class="sr-only" for="review-notes-{{ $admission->id }}">Review notes</label>
                                <textarea
                                    class="form-control form-control-sm"
                                    id="review-notes-{{ $admission->id }}"
                                    name="review_notes"
                                    rows="2"
                                    maxlength="4000"
                                    placeholder="Add a review note (optional)"
                                >{{ $admission->review_notes }}</textarea>
                                <button class="btn btn-sm btn-primary" type="submit">Save decision</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty-state admission-empty-state">
                                <span class="empty-state-mark"><i class="fa-regular fa-folder-open" aria-hidden="true"></i></span>
                                <h3>No applications found</h3>
                                <p>Try changing the status filter or check back when a new application arrives.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-admin.table>

        @if ($admissions->hasPages())
            <div class="admission-pagination">{{ $admissions->links() }}</div>
        @endif
    </section>
@endsection
