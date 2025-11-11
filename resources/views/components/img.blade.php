<img
    src="{{ $src }}"
    @if($srcset) srcset="{{ $srcset }}" @endif
    @if($sizes) sizes="{{ $sizes }}" @endif
    @if($alt) alt="{{ $alt }}" @else alt="" @endif
    @if($class) class="{{ $class }}" @endif
    @foreach($seoAttributes ?? [] as $attribute => $value)
        {{ $attribute }}="{{ e($value) }}"
    @endforeach
/>

@if(!empty($structuredData))
    <script type="application/ld+json">{!! $structuredData !!}</script>
@endif
