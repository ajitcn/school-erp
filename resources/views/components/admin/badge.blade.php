@props(['tone'=>'slate'])
@php($styles=['success'=>'badge-success','warning'=>'badge-warning','danger'=>'badge-danger','info'=>'badge-info','primary'=>'badge-primary','indigo'=>'badge-primary','slate'=>'badge-secondary'])
<span {{ $attributes->merge(['class'=>'badge '.$styles[$tone]]) }}>{{ $slot }}</span>
