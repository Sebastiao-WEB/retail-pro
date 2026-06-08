@php
    $statusCode = (string) ($exception->getStatusCode() ?? '400');
@endphp

<x-errors.page :code="$statusCode" error-key="4xx" />
