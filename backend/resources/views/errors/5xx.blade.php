@php
    $statusCode = (string) ($exception->getStatusCode() ?? '500');
@endphp

<x-errors.page :code="$statusCode" error-key="5xx" />
