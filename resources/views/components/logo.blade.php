@props(['class' => 'size-10'])

<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 48 48" fill="none" aria-hidden="true">
    <rect width="48" height="48" rx="12" fill="#1c1917"/>
    <path d="M14 20l4-6h12l4 6-10 14-10-14z" fill="#d0a546"/>
    <path d="M14 20h20M18 14l6 20M30 14l-6 20" stroke="#7d5520" stroke-width="1.2" stroke-linejoin="round"/>
</svg>
