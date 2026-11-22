@props(['items'=>[],'active'=>null])
<ul class="nav nav-tabs mb-4">@foreach($items as $key=>$label)<li class="nav-item"><a class="nav-link {{ ($active??array_key_first($items))===$key?'active':'' }}" href="#{{ $key }}" data-toggle="tab">{{ $label }}</a></li>@endforeach</ul>
