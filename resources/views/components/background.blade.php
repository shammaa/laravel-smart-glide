@php
    $classes = $class ?? '';
    $baseStyle = $styles['base'] ?? '';
    $responsiveRules = [];

    foreach ($styles as $key => $value) {
        if ($key === 'base') {
            continue;
        }

        $responsiveRules[] = "{$key} { .smart-glide-bg { {$value} } }";
    }

    $responsive = implode(' ', $responsiveRules);
@endphp

<div class="smart-glide-bg {{ $classes }}"
     style="{{ $baseStyle }}"
    @foreach($seoAttributes ?? [] as $attribute => $value)
        {{ $attribute }}="{{ e($value) }}"
    @endforeach
>
    @if($placeholder)
        <div class="smart-glide-bg__placeholder" data-src="{{ $placeholder }}"></div>
    @endif
</div>

@if($responsive)
    <style>{{ $responsive }}</style>
@endif

@if(!empty($structuredData))
    <script type="application/ld+json">{!! $structuredData !!}</script>
@endif

