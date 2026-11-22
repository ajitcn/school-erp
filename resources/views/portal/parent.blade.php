@extends('layouts.portal')

@section('content')
    <header class="portal-parent-intro">
        <div>
            <span class="portal-eyebrow">Parent portal</span>
            <h1>Welcome to your family dashboard</h1>
            <p>Pick a student to see attendance, fees, results, and school updates in one place.</p>
        </div>
        @if ($children->isNotEmpty())
            <span class="portal-child-count">
                <i class="fa-solid fa-children" aria-hidden="true"></i>
                {{ $children->count() }} {{ \Illuminate\Support\Str::plural('student', $children->count()) }} linked
            </span>
        @endif
    </header>

    @if ($children->isEmpty())
        <x-admin.card>
            <x-admin.empty-state
                title="No linked students yet"
                description="Ask the school or college office to connect your parent account to your child’s student record."
            />
        </x-admin.card>
    @else
        <div class="portal-child-grid">
            @foreach ($children as $child)
                @php
                    $activeEnrollment = $child->enrollments
                        ->where('status', 'active')
                        ->sortByDesc('created_at')
                        ->first();
                    $classOrCourse = $activeEnrollment?->schoolClass?->name ?? $activeEnrollment?->course?->name;
                    $sectionOrSemester = $activeEnrollment?->section?->name ?? $activeEnrollment?->semester?->name;
                @endphp

                <article class="admin-card portal-child-card">
                    <div class="portal-child-card-heading">
                        <span class="portal-child-avatar" aria-hidden="true">
                            {{ strtoupper(substr($child->first_name, 0, 1).substr($child->last_name ?? '', 0, 1)) }}
                        </span>
                        <div>
                            <h2>{{ $child->first_name }} {{ $child->last_name }}</h2>
                            <span class="portal-child-id">Student ID · {{ $child->student_id }}</span>
                        </div>
                    </div>

                    <div class="portal-child-placement">
                        <span><i class="fa-solid fa-location-dot" aria-hidden="true"></i>{{ $child->branch?->name ?? 'Campus not assigned' }}</span>
                        <span>
                            <i class="fa-solid fa-book-open" aria-hidden="true"></i>
                            @if ($classOrCourse)
                                {{ $classOrCourse }}{{ $sectionOrSemester ? ' · '.$sectionOrSemester : '' }}
                            @else
                                No active class or course
                            @endif
                        </span>
                        <span><i class="fa-regular fa-calendar" aria-hidden="true"></i>{{ $activeEnrollment?->academicYear?->name ?? 'Academic year not assigned' }}</span>
                    </div>

                    <div class="portal-child-card-actions">
                        <form method="post" action="{{ route('portal.children.select') }}">
                            @csrf
                            <input type="hidden" name="student_id" value="{{ $child->id }}">
                            <button class="btn btn-primary" type="submit">
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>Open student dashboard
                            </button>
                        </form>
                        <a class="btn btn-outline-secondary" href="{{ route('portal.attendance', $child) }}">
                            <i class="fa-regular fa-calendar-check" aria-hidden="true"></i>Attendance
                        </a>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
