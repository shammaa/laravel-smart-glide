<img
    src="{{ $src }}"
    @if($srcset) srcset="{{ $srcset }}" @endif
    @if($sizes) sizes="{{ $sizes }}" @endif
    @if($alt) alt="{{ $alt }}" @else alt="" @endif
    @if($class) class="{{ $class }}" @endif
    @if($style) style="{{ $style }}" @endif
    @if($width) width="{{ $width }}" @endif
    @if($height) height="{{ $height }}" @endif
    @foreach($seoAttributes ?? [] as $attribute => $value)
        {{ $attribute }}="{{ e($value) }}"
    @endforeach
/>

@if(!empty($structuredData))
    <script type="application/ld+json">{!! $structuredData !!}</script>
@endif
