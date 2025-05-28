<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Role;
use App\Models\User;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @param string $role
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $role)
    {
        if (!Auth::check()) {
            return response()->json([
                'message' => 'No autenticado',
            ], 401);
        }

        $user = Auth::user();
        $userRole = Role::find($user->role_id);

        if (!$userRole || $userRole->nombre != $role) {
            return response()->json([
                'message' => 'Acceso denegado. No tienes los permisos necesarios.',
            ], 403);
        }

        return $next($request);
    }
}
