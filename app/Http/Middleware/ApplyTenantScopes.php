<?php

namespace App\Http\Middleware;

use App\Domain\Shared\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyTenantScopes
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! empty(auth()->user()) && Filament::getTenant() != null) {
            setPermissionsTeamId(Filament::getTenant()->id);
        }

        User::addGlobalScope(
            fn (Builder $query) => $query->whereRelation('organizations', 'organizations.id', Filament::getTenant()->id),
        );

        return $next($request);
    }
}
