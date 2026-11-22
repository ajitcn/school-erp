@props(['title', 'description' => null])

<header class="content-heading">
    <div class="content-heading-copy">
        <h2>{{ $title }}</h2>

        @if ($description)
            <p>{{ $description }}</p>
        @endif
    </div>

    @if (isset($actions))
        <div class="page-actions" aria-label="Page actions">
            {{ $actions }}
        </div>
    @endif
</header>
