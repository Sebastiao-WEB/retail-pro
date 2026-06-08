<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Web\Concerns\AuthorizesAdminWeb;
use App\Http\Controllers\Admin\Web\Concerns\RespondsAsJson;
use App\Models\CashSession;
use App\Models\Register;
use Illuminate\Http\Request;

class CashSessionWebController extends Controller
{
    use AuthorizesAdminWeb;
    use RespondsAsJson;

    public function activeIndex(Request $request)
    {
        $this->authorizeAdmin('cash_sessions.view');

        $search = $request->string('search')->toString();
        $registerFilter = $request->string('registerFilter')->toString();

        $sessoes = $this->baseQuery('OPEN', $search, $registerFilter)
            ->latest('opened_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.cash-sessions.active', [
            'sessoes' => $sessoes,
            'search' => $search,
            'registerFilter' => $registerFilter,
            'registers' => Register::query()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function closedIndex(Request $request)
    {
        $this->authorizeAdmin('cash_sessions.view');

        $search = $request->string('search')->toString();
        $registerFilter = $request->string('registerFilter')->toString();

        $fechos = $this->baseQuery('CLOSED', $search, $registerFilter)
            ->latest('closed_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.cash-sessions.closed', [
            'fechos' => $fechos,
            'search' => $search,
            'registerFilter' => $registerFilter,
            'registers' => Register::query()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function show(CashSession $cashSession)
    {
        $this->authorizeAdmin('cash_sessions.view');

        $detalhe = CashSession::query()
            ->with(['register', 'user'])
            ->findOrFail($cashSession->id);

        return $this->jsonOk($this->serializeSession($detalhe));
    }

    private function baseQuery(string $status, string $search, string $registerFilter)
    {
        return CashSession::query()
            ->with(['register', 'user'])
            ->where('status', $status)
            ->when($registerFilter !== '', fn ($q) => $q->where('register_id', $registerFilter))
            ->when($search !== '', function ($q) use ($search, $status) {
                $termo = $search;
                $q->where(function ($inner) use ($termo, $status) {
                    if ($status === 'CLOSED') {
                        $inner->where('note', 'like', "%{$termo}%");
                    }
                    $inner->orWhereHas('register', fn ($reg) => $reg->where('name', 'like', "%{$termo}%")->orWhere('code', 'like', "%{$termo}%"))
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$termo}%"));
                });
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSession(CashSession $session): array
    {
        return [
            'id' => $session->id,
            'status' => $session->status,
            'register' => $session->register?->name ?? '—',
            'register_code' => $session->register?->code ?? '—',
            'operator' => $session->user?->name ?? '—',
            'opened_at' => optional($session->opened_at)->format('d/m/Y H:i'),
            'closed_at' => optional($session->closed_at)->format('d/m/Y H:i'),
            'opening_float' => number_format((float) $session->opening_float, 2, ',', '.'),
            'closing_cash' => number_format((float) ($session->closing_cash ?? 0), 2, ',', '.'),
            'expected_cash' => number_format((float) ($session->expected_cash ?? 0), 2, ',', '.'),
            'difference' => number_format((float) ($session->difference ?? 0), 2, ',', '.'),
            'total_sales' => number_format((float) ($session->total_sales ?? 0), 2, ',', '.'),
            'note' => (string) ($session->note ?? ''),
        ];
    }
}
