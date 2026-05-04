<?php

namespace App\Http\Middleware;

use App\Services\SettingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! SettingService::get('system.maintenance_mode', false)) {
            return $next($request);
        }

        // Admins (anyone with settings.view) pass through
        $user = $request->user();
        if ($user) {
            $user->loadMissing('role.permissions');
            if ($user->hasPermission('settings.view')) {
                return $next($request);
            }
        }

        abort(503, 'The site is temporarily down for maintenance. Please check back soon.');
    }
}
