@props(['name'])
<svg {{ $attributes->class(['directory-icon']) }} width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('chevron')
            <path d="m6 9 6 6 6-6" />
            @break
        @case('verified')
            <circle cx="12" cy="12" r="9" /><path d="m8 12 2.5 2.5L16 9" />
            @break
        @case('circle-check')
            <circle cx="12" cy="12" r="9" /><path d="m8.5 12 2.25 2.25L15.5 9.5" />
            @break
        @case('x-circle')
            <circle cx="12" cy="12" r="9" /><path d="m9 9 6 6m0-6-6 6" />
            @break
        @case('info')
            <circle cx="12" cy="12" r="9" /><path d="M12 11v5m0-8h.01" />
            @break
        @case('education')
            <path d="m3 9 9-5 9 5-9 5-9-5Zm4 3v5c3 3 7 3 10 0v-5M21 9v7" />
            @break
        @case('book-open')
            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2Z" /><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7Z" />
            @break
        @case('mode')
            <rect x="3" y="4" width="18" height="13" rx="2" /><path d="M8 21h8m-4-4v4" />
            @break
        @case('location')
            <path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z" /><circle cx="12" cy="10" r="2.5" />
            @break
        @case('map-pin')
            <path d="M20 10c0 5-5.5 10.2-7.4 11.8a1 1 0 0 1-1.2 0C9.5 20.2 4 15 4 10a8 8 0 1 1 16 0Z" /><circle cx="12" cy="10" r="2.75" />
            @break
        @case('arrow')
            <path d="M5 12h14m-5-5 5 5-5 5" />
            @break
        @case('arrow-left')
            <path d="M19 12H5m5-5-5 5 5 5" />
            @break
        @case('share')
            <circle cx="18" cy="5" r="2.5" /><circle cx="6" cy="12" r="2.5" /><circle cx="18" cy="19" r="2.5" /><path d="m8.2 10.8 7.6-4.4m-7.6 6.8 7.6 4.4" />
            @break
        @case('eye')
            <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" /><circle cx="12" cy="12" r="2.5" />
            @break
        @case('edit')
            <path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z" />
            @break
        @case('briefcase')
            <rect x="3" y="7" width="18" height="13" rx="2" /><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18" />
            @break
        @case('send')
            <path d="m22 2-7 20-4-9-9-4Z" /><path d="M22 2 11 13" />
            @break
        @case('bookmark')
            <path d="M6 3h12v18l-6-4-6 4Z" />
            @break
        @case('flag')
            <path d="M5 21V4m0 1h11l-1.5 4L16 13H5" />
            @break
        @case('shield')
            <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z" /><path d="m9 12 2 2 4-5" />
            @break
        @case('users')
            <circle cx="9" cy="8" r="3.5" /><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M17.5 14a6 6 0 0 1 4 6" />
            @break
        @case('support')
            <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z" /><path d="M8 9h8M8 13h5" />
            @break
        @case('document')
            <path d="M6 2h8l4 4v16H6Z" /><path d="M14 2v5h5M9 12h6m-6 4h6" />
            @break
        @case('file-check')
            <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5Z" /><path d="M14 2v6h6m-11 7 2 2 4-4" />
            @break
        @case('menu')
            <path d="M4 7h16M4 12h16M4 17h16" />
            @break
        @case('check')
            <path d="m5 12 4 4L19 6" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="8.5" /><path d="M12 7v5l3 2" />
            @break
        @case('filter')
            <path d="M4 7h7m4 0h5M4 17h3m4 0h9" /><circle cx="13" cy="7" r="2" /><circle cx="9" cy="17" r="2" />
            @break
        @case('search')
            <circle cx="11" cy="11" r="6.5" /><path d="m16 16 4 4" />
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2" /><path d="M7 3v4m10-4v4M3 10h18" />
            @break
        @case('calendar-days')
            <rect x="3" y="4" width="18" height="18" rx="2" /><path d="M8 2v4m8-4v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01" />
            @break
        @case('bell')
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" /><path d="M10 21h4" />
            @break
        @case('home')
            <path d="m3 11 9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z" />
            @break
        @case('chevron-right')
            <path d="m9 18 6-6-6-6" />
            @break
        @case('list')
            <path d="M9 6h11M9 12h11M9 18h11" /><circle cx="4" cy="6" r=".8" fill="currentColor" /><circle cx="4" cy="12" r=".8" fill="currentColor" /><circle cx="4" cy="18" r=".8" fill="currentColor" />
            @break
        @case('request')
            <rect x="5" y="3" width="14" height="18" rx="2" /><path d="M9 3.5h6V7H9zM9 12h6m-6 4h4" />
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14" />
            @break
        @case('sort')
            <path d="M4 7h16M7 12h10m-7 5h4" />
            @break
        @case('wallet')
            <path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H19a1 1 0 0 1 1 1v2H6.5A2.5 2.5 0 0 0 4 10.5v7A2.5 2.5 0 0 0 6.5 20H20v-9H6.5A2.5 2.5 0 0 1 4 8.5z" /><circle cx="16" cy="15.5" r=".8" fill="currentColor" />
            @break
        @case('user')
            <circle cx="12" cy="8" r="4" /><path d="M4.5 21a7.5 7.5 0 0 1 15 0" />
            @break
        @case('user-round')
            <circle cx="12" cy="8" r="5" /><path d="M20 21a8 8 0 0 0-16 0" />
            @break
        @case('mail')
            <rect x="3" y="5" width="18" height="14" rx="2" /><path d="m4 7 8 6 8-6" />
            @break
        @case('phone')
            <path d="M7 3H4.5A1.5 1.5 0 0 0 3 4.5C3 13.6 10.4 21 19.5 21a1.5 1.5 0 0 0 1.5-1.5V17l-4-1-1 2a14 14 0 0 1-10-10l2-1-1-4Z" />
            @break
        @case('lock')
            <rect x="5" y="10" width="14" height="11" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" />
            @break
        @case('save')
            <path d="M5 3h12l2 2v16H5z" /><path d="M8 3v6h8V3M8 21v-7h8v7" />
            @break
        @case('logout')
            <path d="M10 4H5v16h5M14 8l4 4-4 4m4-4H9" />
            @break
    @endswitch
</svg>
