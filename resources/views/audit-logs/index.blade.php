@extends('layouts.app')
@section('title', 'Audit Log')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Audit Log</h1>
        <p class="text-sm text-gray-500 mt-0.5">Track who changed what, and when.</p>
    </div>
</div>

{{-- ── Filters ────────────────────────────────────────────────────────────── --}}
<form method="GET" action="{{ route('audit-logs.index') }}" class="bg-white border border-gray-200 rounded-2xl p-4 mb-5">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
        <div>
            <label class="form-label">User</label>
            <select name="user_id" class="form-input">
                <option value="">All users</option>
                @foreach ($users as $u)
                <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>
                    {{ $u->name }}
                </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Action</label>
            <select name="action" class="form-input">
                <option value="">All actions</option>
                <option value="created"              {{ request('action') === 'created'              ? 'selected' : '' }}>Created</option>
                <option value="updated"              {{ request('action') === 'updated'              ? 'selected' : '' }}>Updated</option>
                <option value="deleted"              {{ request('action') === 'deleted'              ? 'selected' : '' }}>Deleted</option>
                <option value="permissions_changed" {{ request('action') === 'permissions_changed' ? 'selected' : '' }}>Permissions Changed</option>
            </select>
        </div>
        <div>
            <label class="form-label">Model (partial name)</label>
            <input type="text" name="model" value="{{ request('model') }}"
                   placeholder="e.g. Household" class="form-input">
        </div>
        <div class="flex gap-2">
            <div class="flex-1">
                <label class="form-label">From</label>
                <input type="date" name="from" value="{{ request('from') }}" class="form-input">
            </div>
            <div class="flex-1">
                <label class="form-label">To</label>
                <input type="date" name="to" value="{{ request('to') }}" class="form-input">
            </div>
        </div>
    </div>
    <div class="flex gap-2">
        <button type="submit" class="btn-primary text-sm">Apply Filters</button>
        <a href="{{ route('audit-logs.index') }}" class="btn-secondary text-sm">Reset</a>
    </div>
</form>

{{-- ── Results ────────────────────────────────────────────────────────────── --}}
<div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
        <span class="text-sm font-semibold text-gray-700">
            {{ number_format($logs->total()) }} {{ Str::plural('entry', $logs->total()) }}
        </span>
    </div>

    @if ($logs->isEmpty())
    <div class="px-5 py-16 text-center text-sm text-gray-400">No audit log entries found.</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">When</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Who</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Action</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Model</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Changes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($logs as $log)
                @php
                $actionColor = match ($log->action) {
                    'created' => 'bg-green-100 text-green-700',
                    'updated' => 'bg-blue-100 text-blue-700',
                    'deleted' => 'bg-red-100 text-red-700',
                    'permissions_changed' => 'bg-purple-100 text-purple-700',
                    default   => 'bg-gray-100 text-gray-600',
                };
                $actionLabel = $log->action === 'permissions_changed' ? 'Permissions' : ucfirst($log->action);
                @endphp
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                        {{ $log->created_at->format('M j, Y g:i A') }}
                    </td>
                    <td class="px-4 py-3 font-medium text-gray-800">
                        {{ $log->user?->name ?? '—' }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="badge {{ $actionColor }}">{{ $actionLabel }}</span>
                    </td>
                    <td class="px-4 py-3 text-gray-700">
                        {{ $log->targetLabel() }} #{{ $log->target_id }}
                    </td>
                    <td class="px-4 py-3 max-w-xs">
                        @if ($log->action === 'permissions_changed')
                            @php
                                $before  = collect($log->before_json['permissions'] ?? []);
                                $after   = collect($log->after_json['permissions']  ?? []);
                                $granted = $after->diff($before)->values();
                                $revoked = $before->diff($after)->values();
                            @endphp
                            @if ($granted->isNotEmpty())
                            <div class="text-xs text-green-600 truncate">
                                <span class="font-medium">+ Granted:</span> {{ $granted->implode(', ') }}
                            </div>
                            @endif
                            @if ($revoked->isNotEmpty())
                            <div class="text-xs text-red-500 truncate">
                                <span class="font-medium">− Revoked:</span> {{ $revoked->implode(', ') }}
                            </div>
                            @endif
                            @if ($granted->isEmpty() && $revoked->isEmpty())
                            <span class="text-xs text-gray-400">No change</span>
                            @endif
                        @elseif ($log->action === 'updated' && $log->before_json)
                            @foreach ($log->after_json ?? [] as $field => $newVal)
                            <div class="text-xs text-gray-500 truncate">
                                <span class="font-medium text-gray-700">{{ $field }}:</span>
                                <span class="line-through text-red-500">{{ is_array($log->before_json[$field] ?? null) ? '…' : ($log->before_json[$field] ?? '—') }}</span>
                                →
                                <span class="text-green-600">{{ is_array($newVal) ? '…' : $newVal }}</span>
                            </div>
                            @endforeach
                        @elseif ($log->action === 'created')
                            <span class="text-xs text-gray-400">New record</span>
                        @elseif ($log->action === 'deleted')
                            <span class="text-xs text-red-400">Record removed</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($logs->hasPages())
    <div class="px-5 py-3 border-t border-gray-100">
        {{ $logs->links() }}
    </div>
    @endif
    @endif
</div>

@endsection
