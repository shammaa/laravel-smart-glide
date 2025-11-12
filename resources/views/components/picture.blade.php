<picture @if($class) class="{{ $class }}" @endif @if($style) style="{{ $style }}" @endif @if($width) width="{{ $width }}" @endif @if($height) height="{{ $height }}" @endif>
    @foreach($sources as $source)
        <source
            @if($source['media']) media="{{ $source['media'] }}" @endif
            @if($source['type']) type="{{ $source['type'] }}" @endif
            @if($source['srcset']) srcset="{{ $source['srcset'] }}" @endif
            @if($source['sizes']) sizes="{{ $source['sizes'] }}" @endif
        @if($source['src']) src="{{ $source['src'] }}" @endif
        />
    @endforeach
    <img
        src="{{ $img['src'] }}"
        @if($img['srcset']) srcset="{{ $img['srcset'] }}" @endif
        @if($img['sizes']) sizes="{{ $img['sizes'] }}" @endif
        @if($img['alt']) alt="{{ $img['alt'] }}" @else alt="" @endif
        @if($img['class']) class="{{ $img['class'] }}" @endif
    @if($img['style']) style="{{ $img['style'] }}" @endif
    @if($img['width']) width="{{ $img['width'] }}" @endif
    @if($img['height']) height="{{ $img['height'] }}" @endif
        @foreach($img['seoAttributes'] ?? [] as $attribute => $value)
            {{ $attribute }}="{{ e($value) }}"
        @endforeach
    />
</picture>

@if(!empty($structuredData))
    <script type="application/ld+json">{!! $structuredData !!}</script>
@endif

