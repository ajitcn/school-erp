@props(['href','label'=>'','icon'=>'*','active'=>false,'external'=>false])
@php
    $icons = [
        'D' => 'fa-gauge-high', 'S' => 'fa-user-graduate', 'A' => 'fa-user-plus', 'T' => 'fa-calendar-check',
        'P' => 'fa-users', 'B' => 'fa-code-branch', '*' => 'fa-circle', 'F' => 'fa-file-invoice-dollar',
        'E' => 'fa-wallet', 'C' => 'fa-right-left', 'R' => 'fa-chart-column', 'U' => 'fa-user-gear',
        'I' => 'fa-file-import', 'L' => 'fa-clipboard-list',
        'N' => 'fa-bell', 'M' => 'fa-mobile-screen-button', 'Q' => 'fa-message', '$' => 'fa-credit-card',
    ];
@endphp
<a href="{{ $href }}" class="admin-menu-item {{ $active ? 'active' : '' }}" aria-current="{{ $active ? 'page' : 'false' }}" aria-label="{{ $label ?: trim(strip_tags($slot)) }}" title="{{ $label ?: trim(strip_tags($slot)) }}" @if($external) target="_blank" rel="noopener noreferrer" @endif><span class="admin-menu-icon" aria-hidden="true"><i class="fa-solid {{ $icons[$icon] ?? 'fa-circle' }}"></i></span><span>{{ $label ?: $slot }}</span></a>
