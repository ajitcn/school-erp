@extends('layouts.app')

@section('content')
    <x-admin.page-header title="Edit fee plan" description="Update this reusable plan’s name and academic target." />
    <section class="admin-card">
        <form method="post" action="{{ route('fees.structures.update', $structure) }}" class="p-4">
            @csrf @method('put')
            <div class="form-row">
                <div class="form-group col-md-6"><label for="name">Plan name</label><input id="name" name="name" class="form-control" value="{{ old('name', $structure->name) }}" required></div>
                <div class="form-group col-md-6"><label for="default_amount">Standard amount</label><div class="input-group"><div class="input-group-prepend"><span class="input-group-text">₹</span></div><input id="default_amount" name="default_amount" type="number" min="0.01" step="0.01" class="form-control" value="{{ old('default_amount', $structure->default_amount) }}" required></div><small class="form-text text-muted">New student schedules will start with this amount.</small>@error('default_amount')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="form-group col-md-6"><label for="academic_year_id">Academic year <span class="text-muted">(optional)</span></label><select id="academic_year_id" name="academic_year_id" class="form-control"><option value="">Reusable across years</option>@foreach($years as $year)<option value="{{ $year->id }}" @selected(old('academic_year_id', $structure->academic_year_id) == $year->id)>{{ $year->name }}</option>@endforeach</select></div>
                <div class="form-group col-md-4"><label for="school_class_id">Class <span class="text-muted">(optional)</span></label><select id="school_class_id" name="school_class_id" class="form-control"><option value="">Any class</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected(old('school_class_id', $structure->school_class_id) == $class->id)>{{ $class->name }}</option>@endforeach</select></div>
                <div class="form-group col-md-4"><label for="course_id">Course <span class="text-muted">(optional)</span></label><select id="course_id" name="course_id" class="form-control"><option value="">Any course</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected(old('course_id', $structure->course_id) == $course->id)>{{ $course->name }}</option>@endforeach</select></div>
                <div class="form-group col-md-4"><label for="semester_id">Semester <span class="text-muted">(optional)</span></label><select id="semester_id" name="semester_id" class="form-control"><option value="">Any semester</option>@foreach($semesters as $semester)<option value="{{ $semester->id }}" @selected(old('semester_id', $structure->semester_id) == $semester->id)>{{ $semester->course?->name }} · {{ $semester->name }}</option>@endforeach</select></div>
            </div>
            <button class="btn btn-primary" type="submit">Save plan</button>
            <a class="btn btn-outline-secondary ml-2" href="{{ route('fees.structures.index') }}">Back to fee plans</a>
        </form>
    </section>
@endsection
