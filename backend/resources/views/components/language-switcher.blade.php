@php
    use App\Support\SupportedLocales;
    $currentLocale = app()->getLocale();
@endphp

<div class="flex items-center gap-1 rounded-lg border border-slate-200 bg-white p-0.5">
    @foreach (SupportedLocales::labels() as $code => $label)
        <button
            type="button"
            onclick="window.retailLocale?.switch(@js($code))"
            @class([
                'rounded-md px-2 py-1 text-[11px] font-semibold transition',
                'bg-[var(--gold)] text-black' => $currentLocale === $code,
                'text-slate-600 hover:bg-slate-50' => $currentLocale !== $code,
            ])
            title="{{ $label }}"
        >
            {{ $code === 'pt_MZ' ? 'PT' : 'SO' }}
        </button>
    @endforeach
</div>
