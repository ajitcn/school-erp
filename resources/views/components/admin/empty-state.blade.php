@props(['title' => 'Nothing here yet', 'description' => null, 'action' => null])

<div class="empty-state">
    <div class="empty-state-mark" aria-hidden="true">—</div>
    <h3>{{ $title }}</h3>

    @if ($description)
        <p>{{ $description }}</p>
    @endif

    @if ($action)
        {{ $action }}
    @endif
</div>
