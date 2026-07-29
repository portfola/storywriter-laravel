@php
// Renders a <button> when no href is given. Log Out posts a form rather than
// following a link, and a button submits it on its own -- an <a> needs script
// in an onclick attribute, which the content security policy denies.
$classes = 'block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out';
@endphp

@if ($attributes->has('href'))
    <a {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
