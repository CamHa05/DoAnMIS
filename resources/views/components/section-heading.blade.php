@props(['eyebrow', 'title', 'copy' => null])
<div class="section-heading">
    <p class="eyebrow">{{ $eyebrow }}</p>
    <h2>{{ $title }}</h2>@if ($copy)<p>{{ $copy }}</p>@endif
</div>