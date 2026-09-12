@props(['html' => null, 'block' => null])
@php
    $safeHtml = \Mews\Purifier\Facades\Purifier::clean($html ?? (string) data_get($block?->content, 'body', ''), 'terms');
@endphp
<div class="terms-content text-body-m text-ink-900">{!! $safeHtml !!}</div>
