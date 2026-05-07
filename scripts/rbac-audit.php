<?php

/**
 * RBAC audit — classifies every authenticated route as:
 *   - GATED   : has at least one `permission:...` middleware
 *   - POLICY  : controller probably calls $this->authorize() (heuristic via grep)
 *   - LEAK    : behind `auth` but has neither route middleware nor a controller authorize
 *
 * Usage:  php artisan route:list --json | php scripts/rbac-audit.php
 *
 * The output is grouped, so the human-readable list of LEAKs is the
 * actionable backlog for adding gates.
 */

$json = stream_get_contents(STDIN);
$routes = json_decode($json, true) ?? [];

$gated = [];
$policy = [];
$leak = [];
$skipPublic = [];

foreach ($routes as $r) {
    $mw = $r['middleware'] ?? [];
    $action = $r['action'] ?? '';
    $name = $r['name'] ?? '(unnamed)';
    $method = $r['method'] ?? 'GET';
    $uri = $r['uri'] ?? '';

    // Skip non-auth routes (public registration, login, health checks, etc.)
    if (! in_array('auth', $mw, true) && ! in_array('event-day-or-auth', $mw, true)) {
        $skipPublic[] = compact('method', 'uri', 'name');
        continue;
    }

    $hasPermissionMiddleware = false;
    foreach ($mw as $m) {
        if (str_starts_with($m, 'permission:')) {
            $hasPermissionMiddleware = true;
            break;
        }
    }

    if ($hasPermissionMiddleware) {
        $gated[] = compact('method', 'uri', 'name', 'action', 'mw');
        continue;
    }

    // Heuristic: does the controller method call $this->authorize() or have a FormRequest with authorize()?
    $controllerHasAuthorize = false;
    if (preg_match('/^(.+?)@(\w+)$/', $action, $m2)) {
        $class = $m2[1];
        $method2 = $m2[2];
        $relPath = str_replace('App\\', 'app/', $class) . '.php';
        $relPath = str_replace('\\', '/', $relPath);
        $absPath = __DIR__ . '/../' . $relPath;
        if (is_file($absPath)) {
            $src = file_get_contents($absPath);
            // Heuristic 1: $this->authorize anywhere in the controller (over-approximation;
            // misses methods that don't authorize).
            if (preg_match('/function\s+' . preg_quote($method2) . '\s*\([^)]*\)[^{]*\{(.+?)\n\s*\}\s*\n/s', $src, $mm)) {
                if (str_contains($mm[1], '$this->authorize')) {
                    $controllerHasAuthorize = true;
                }
                // Heuristic 2: method takes a FormRequest with authorize() that calls hasPermission
                if (preg_match('/function\s+' . preg_quote($method2) . '\s*\(\s*([A-Za-z\\\\]+Request)\s+/', $src, $rm)) {
                    $reqClass = $rm[1];
                    $reqPath = __DIR__ . '/../app/Http/Requests/' . basename(str_replace('\\', '/', $reqClass)) . '.php';
                    if (is_file($reqPath)) {
                        $reqSrc = file_get_contents($reqPath);
                        if (str_contains($reqSrc, 'hasPermission')) {
                            $controllerHasAuthorize = true;
                        }
                    }
                }
            }
        }
    }

    if ($controllerHasAuthorize) {
        $policy[] = compact('method', 'uri', 'name', 'action');
    } else {
        $leak[] = compact('method', 'uri', 'name', 'action');
    }
}

echo "═══════════════════════════════════════════════════════════════\n";
echo " RBAC ROUTE AUDIT\n";
echo "═══════════════════════════════════════════════════════════════\n\n";
printf("Authenticated routes: %d\n", count($gated) + count($policy) + count($leak));
printf("  GATED  (route middleware permission:*)        : %d\n", count($gated));
printf("  POLICY (controller \$this->authorize() found)  : %d\n", count($policy));
printf("  LEAK   (no route middleware AND no authorize) : %d\n", count($leak));
echo "\n";

if (! empty($leak)) {
    echo "─── LEAK — routes accessible to ANY authenticated user ─────────\n";
    foreach ($leak as $r) {
        printf("  %-7s %-55s %s\n", $r['method'], $r['uri'], $r['name']);
        printf("           %s\n", $r['action']);
    }
    echo "\n";
}

if (! empty($policy)) {
    echo "─── POLICY-only — relies on controller authorize() ─────────────\n";
    foreach ($policy as $r) {
        printf("  %-7s %-55s %s\n", $r['method'], $r['uri'], $r['name']);
    }
    echo "\n";
}

echo "─── GATED — has route-level permission middleware ──────────────\n";
foreach ($gated as $r) {
    $perms = array_filter($r['mw'], fn ($m) => str_starts_with($m, 'permission:'));
    printf("  %-7s %-55s %s [%s]\n", $r['method'], $r['uri'], $r['name'], implode(', ', $perms));
}
