@props(['label', 'portal' => false])
<div class="dropdown d-inline-block {{ $portal ? 'admin-action-dropdown' : '' }}" data-dropdown @if($portal) data-dropdown-portal @endif>
    <button class="btn btn-outline-secondary dropdown-toggle {{ $portal ? 'btn-sm admin-action-trigger' : '' }}" type="button" data-dropdown-toggle aria-expanded="false">{{ $label }}</button>
    <div class="dropdown-menu shadow {{ $portal ? 'admin-action-menu' : '' }}" data-dropdown-panel hidden>{{ $slot }}</div>
</div>
