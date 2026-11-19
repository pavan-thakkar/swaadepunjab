<?php

namespace App\Filament\Concerns;

/**
 * Role-based access for admin screens.
 * A resource declares roleAccess(): ['view' => [...], 'edit' => [...], 'delete' => [...]].
 * Roles: owner, manager, staff. Missing keys default to owner only.
 */
trait HasRoleAccess
{
    protected static function roleAccess(): array
    {
        return ['view' => ['owner'], 'edit' => ['owner'], 'delete' => ['owner']];
    }

    protected static function userHasAccess(string $kind): bool
    {
        $user = auth()->user();
        $map = static::roleAccess();
        $roles = $map[$kind] ?? ($kind === 'edit' ? ($map['view'] ?? ['owner']) : ['owner']);
        return $user && method_exists($user, 'hasRole') && $user->hasRole($roles);
    }

    public static function canViewAny(): bool
    {
        return static::userHasAccess('view');
    }

    public static function canView($record): bool
    {
        return static::userHasAccess('view');
    }

    public static function canCreate(): bool
    {
        return static::userHasAccess('view') && static::userHasAccess('edit');
    }

    public static function canEdit($record): bool
    {
        return static::userHasAccess('view') && static::userHasAccess('edit');
    }

    public static function canDelete($record): bool
    {
        return static::userHasAccess('delete');
    }

    public static function canDeleteAny(): bool
    {
        return static::userHasAccess('delete');
    }
}
