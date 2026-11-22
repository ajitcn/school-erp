@extends('layouts.app')

@section('content')
    <x-admin.page-header title="Attendance report" description="Filter attendance records by date and status." />
    <x-admin.card class="mb-6"><form class="grid gap-4 md:grid-cols-3 js-filter-form"><div><label for="from_date" class="mb-1.5 block text-sm font-semibold text-slate-700">From date</label><input id="from_date" type="text" class="form-control js-datepicker" name="from_date" value="{{ $d['from_date'] ?? '' }}" autocomplete="off"></div><div><label for="to_date" class="mb-1.5 block text-sm font-semibold text-slate-700">To date</label><input id="to_date" type="text" class="form-control js-datepicker" name="to_date" value="{{ $d['to_date'] ?? '' }}" autocomplete="off"></div><div><label for="status" class="mb-1.5 block text-sm font-semibold text-slate-700">Status</label><select id="status" name="status" class="form-control"><option value="">All statuses</option><option>present</option><option>absent</option><option>late</option><option>half_day</option><option>leave</option></select></div><div><x-admin.button type="submit">Filter</x-admin.button></div></form></x-admin.card>
    <x-admin.card padding="false">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2>Attendance ledger</h2>
            <div class="d-flex flex-wrap" style="gap: .5rem">
                @if (auth()->user()->hasPermission('reports.view'))
                    @foreach (['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'] as $format)
                        <a
                            class="btn btn-sm btn-outline-primary"
                            href="{{ route('reports.export', ['type' => 'attendance', 'format' => $format] + request()->query()) }}"
                        >
                            {{ $format === 'xlsx' ? 'Excel' : strtoupper($format) }}
                        </a>
                    @endforeach
                @endif
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">Print</button>
            </div>
        </div>

        <x-admin.table>
            <thead>
                <tr>
                    <th scope="col">Date</th>
                    <th scope="col">Student</th>
                    <th scope="col">Status</th>
                    <th scope="col">Remarks</th>
                    <th scope="col">Correction</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td>{{ $record->attendance_date->format('d M Y') }}</td>
                        <td>{{ $record->student->first_name }} {{ $record->student->last_name }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $record->status)) }}</td>
                        <td>{{ $record->remarks ?: '—' }}</td>
                        <td>
                            @if (auth()->user()->hasPermission('attendance.mark'))
                                <form method="post" action="{{ route('attendance.corrections.store', $record) }}" class="d-flex align-items-center">
                                    @csrf
                                    <select name="new_status" class="form-control form-control-sm mr-2" required>
                                        <option value="">Change to…</option>
                                        @foreach (['present', 'absent', 'late', 'half_day', 'leave'] as $status)
                                            <option value="{{ $status }}">
                                                {{ ucfirst(str_replace('_', ' ', $status)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input name="reason" class="form-control form-control-sm mr-2" placeholder="Reason" required>
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Request</button>
                                </form>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-5 text-center text-muted">No attendance records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-admin.table>

        <div class="p-3">{{ $records->links() }}</div>
    </x-admin.card>
@endsection
