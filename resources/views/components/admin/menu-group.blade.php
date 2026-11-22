@props(['id','label','icon'=>'*','open'=>false])
@php
    $icons = ['A' => 'fa-graduation-cap', 'F' => 'fa-coins', 'U' => 'fa-users-gear', 'P' => 'fa-earth-americas', 'W' => 'fa-comment-dollar'];
@endphp
<div class="admin-menu-group {{ $open ? 'is-open is-active' : '' }}" data-menu-group>
    <button type="button" class="admin-menu-group-toggle" data-menu-group-toggle aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="{{ $id }}" aria-label="{{ $label }}" title="{{ $label }}"><span class="admin-menu-icon" aria-hidden="true"><i class="fa-solid {{ $icons[$icon] ?? 'fa-folder' }}"></i></span><span>{{ $label }}</span><i class="fa-solid fa-chevron-right admin-menu-chevron" aria-hidden="true"></i></button>
    <div id="{{ $id }}" class="admin-submenu" data-menu-submenu {{ $open ? '' : 'hidden' }}>{{ $slot }}</div>
</div>
