@props(['href','active'=>false,'icon'=>'*'])
<a href="{{ $href }}" class="admin-menu-item {{ $active ? 'active' : '' }}">
    <span class="admin-menu-icon" aria-hidden="true">{{ $icon }}</span>
    <span>{{ $slot }}</span>
</a>
