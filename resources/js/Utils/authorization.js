import { usePage } from "@inertiajs/react";

export function resolveAuthorizationState(auth = {}) {
    return {
        permissions: auth?.permissions ?? {},
        super: auth?.super === true,
    };
}

export function isSuperAdmin(auth = {}) {
    return resolveAuthorizationState(auth).super;
}

export function can(permission, auth = {}) {
    const state = resolveAuthorizationState(auth);

    return state.super || !!state.permissions?.[permission];
}

export function canAny(permissions = [], auth = {}) {
    if (!Array.isArray(permissions) || permissions.length === 0) {
        return false;
    }

    return permissions.some((permission) => can(permission, auth));
}

export function canAll(permissions = [], auth = {}) {
    if (!Array.isArray(permissions) || permissions.length === 0) {
        return false;
    }

    return permissions.every((permission) => can(permission, auth));
}

export function useAuthorization() {
    const { auth } = usePage().props;

    return {
        auth,
        can: (permission) => can(permission, auth),
        canAny: (permissions) => canAny(permissions, auth),
        canAll: (permissions) => canAll(permissions, auth),
        isSuperAdmin: () => isSuperAdmin(auth),
    };
}
