@props([
    'label',
    'value',
    'detail' => null,
    'tone' => 'primary',
    'icon' => 'fa-chart-simple',
])

@php
    $icons = [
        'S' => 'fa-user-graduate',
        'T' => 'fa-chalkboard-user',
        '₹' => 'fa-indian-rupee-sign',
        'E' => 'fa-wallet',
    ];

    $iconClass = $icons[$icon] ?? $icon;
    $allowedTones = ['primary', 'success', 'info', 'warning', 'danger', 'purple', 'slate'];
    $tone = in_array($tone, $allowedTones, true) ? $tone : 'primary';
@endphp

<div class="admin-stat">
    <div class="admin-stat-content">
        <small class="admin-stat-label">{{ $label }}</small>
        <strong class="admin-stat-value">{{ $value }}</strong>

        @if ($detail)
            <span class="admin-stat-detail">{{ $detail }}</span>
        @endif
    </div>

    <span class="stat-icon bg-{{ $tone }} stat-icon-tone-{{ $tone }}" aria-hidden="true">
        <i class="fa-solid {{ str_starts_with($iconClass, 'fa-') ? $iconClass : 'fa-chart-simple' }}"></i>
    </span>
</div>
