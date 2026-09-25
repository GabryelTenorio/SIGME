@props(['name'])

<svg {{ $attributes->merge(['class' => 'ui-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('dashboard')
            <rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="7" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /><rect x="14" y="14" width="7" height="7" rx="1.5" />
            @break
        @case('organization')
            <path d="M4 21V7l8-4 8 4v14" /><path d="M9 21v-5h6v5M8 9h.01M12 9h.01M16 9h.01M8 13h.01M12 13h.01M16 13h.01" />
            @break
        @case('school')
            <path d="m3 10 9-6 9 6" /><path d="M5 9v10h14V9M9 19v-5h6v5" />
            @break
        @case('users')
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
            @break
        @case('occurrence')
            <path d="M12 9v4M12 17h.01" /><path d="M10.3 3.6 2.6 17a2 2 0 0 0 1.74 3h15.32a2 2 0 0 0 1.74-3L13.7 3.6a2 2 0 0 0-3.4 0Z" />
            @break
        @case('environment')
            <path d="M3 21h18M5 21V5h14v16M9 9h.01M15 9h.01M9 13h.01M15 13h.01M9 17h6" />
            @break
        @case('service-order')
            <path d="M14.7 6.3a4 4 0 0 0-5-5L7 4l3 3 2.7-2.7a4 4 0 0 0 2 2Z" /><path d="m5 9-3 3 10 10 3-3M14 14l3-3 5 5-3 3" />
            @break
        @case('notification')
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" /><path d="M10 21h4" />
            @break
        @case('shield')
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" /><path d="m9 12 2 2 4-4" />
            @break
        @case('logout')
            <path d="M10 17l5-5-5-5M15 12H3M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
            @break
        @case('menu')
            <path d="M4 6h16M4 12h16M4 18h16" />
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14" />
            @break
        @case('edit')
            <path d="M12 20h9" /><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z" />
            @break
        @case('check')
            <path d="m5 12 4 4L19 6" />
            @break
        @case('search')
            <circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" />
            @break
        @case('chart')
            <path d="M4 20V10M10 20V4M16 20v-7M22 20V7" />
            @break
        @default
            <circle cx="12" cy="12" r="9" />
    @endswitch
</svg>
