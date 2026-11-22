@extends('layouts.app')
@section('content')
    @php($labels = ['classes' => 'Class', 'sections' => 'Section', 'departments' => 'Department', 'courses' => 'Course', 'semesters' => 'Semester'])
    <x-admin.page-header :title="'Edit '.$labels[$type]" />
    <div class="admin-card p-4">
        <form method="post" action="{{ route('academic.update', [$type, $item->id]) }}">
            @csrf @method('put')
            <div class="form-row">
                <div class="col-md-6 form-group"><label for="name">Name</label><input id="name" class="form-control" name="name" value="{{ old('name', $item->name) }}" required></div>
                @if(in_array($type, ['classes', 'departments', 'courses'], true))
                    <div class="col-md-6 form-group"><label for="code">{{ $type === 'courses' ? 'Course code' : 'Code' }}</label><input id="code" class="form-control" name="code" value="{{ old('code', $item->code) }}" @required($type === 'courses')></div>
                @endif
            </div>
            <button class="btn btn-primary">Update</button>
            <a class="btn btn-outline-secondary ml-2" href="{{ route('academic.index', $type) }}">Cancel</a>
        </form>
    </div>
@endsection
