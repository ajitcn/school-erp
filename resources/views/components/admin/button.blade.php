@props(['variant'=>'primary','type'=>'button'])
@php($styles=['primary'=>'btn-primary','secondary'=>'btn-outline-secondary','danger'=>'btn-danger','ghost'=>'btn-light','success'=>'btn-success'])
<button type="{{ $type }}" {{ $attributes->merge(['class'=>'btn '.$styles[$variant]]) }}>{{ $slot }}</button>
