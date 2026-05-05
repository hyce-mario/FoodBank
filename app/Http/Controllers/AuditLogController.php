<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $query = AuditLog::with('user')
                         ->latest('created_at');

        if ($userId = $request->get('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($action = $request->get('action')) {
            $query->where('action', $action);
        }

        if ($model = $request->get('model')) {
            $query->where('target_type', 'like', "%{$model}%");
        }

        if ($from = $request->get('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        // Per-page selector — allowed values match the audit-logs footer
        // dropdown. Anything outside the whitelist falls back to 25 so a
        // hand-typed `?per_page=99999` can't drag the whole table down.
        $perPage = in_array((int) $request->get('per_page', 25), [15, 25, 50, 100], true)
            ? (int) $request->get('per_page', 25)
            : 25;

        $logs  = $query->paginate($perPage)->withQueryString();
        $users = User::orderBy('name')->get(['id', 'name']);

        return view('audit-logs.index', compact('logs', 'users'));
    }
}
