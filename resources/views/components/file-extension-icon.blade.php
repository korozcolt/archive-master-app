@props([
    'extension' => null,
    'path' => null,
    'size' => 'h-5 w-5',
    'container' => true,
    'containerClass' => '',
])

@php
    $resolvedExtension = $extension;

    if ($resolvedExtension === null && is_string($path)) {
        $resolvedExtension = \App\Support\FileExtensionIcon::extensionFromPath($path);
    }

    $meta = \App\Support\FileExtensionIcon::meta($resolvedExtension);
    $baseContainerClass = trim("flex items-center justify-center rounded-lg {$meta['bg']} {$meta['fg']}");
@endphp

@if($container)
    <div {{ $attributes->merge(['class' => trim("{$baseContainerClass} {$containerClass}")]) }}>
        @svg($meta['icon'], trim($size))
    </div>
@else
    <span {{ $attributes->merge(['class' => trim("inline-flex items-center justify-center {$meta['fg']} {$containerClass}")]) }}>
        @svg($meta['icon'], trim($size))
    </span>
@endif

