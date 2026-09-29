@php
    $content = 'notifications.partials.list';
@endphp

@if ($isAdmin)
    <x-admin-layout title="Thông báo">
        @include($content)
    </x-admin-layout>
@else
    <x-app-layout title="Thông báo · GiaSu" body-class="notifications-page-body">
        @include($content)
    </x-app-layout>
@endif
