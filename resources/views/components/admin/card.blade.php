@props(['padding'=>true])
<section {{ $attributes->merge(['class'=>'admin-card '.($padding?'p-3 p-md-4':'')]) }}>{{ $slot }}</section>
