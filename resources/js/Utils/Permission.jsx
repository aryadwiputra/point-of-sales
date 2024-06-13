import { usePage } from "@inertiajs/react";

export default function hasAnyPermission(permissions) {

    // destruct auth from usepage props
    const { auth } = usePage().props

    // get all permissions from props auth.permissions
    let allPermissions = auth.permissions;

    // define has permission is false
    let hasPermission = false;

    // loop permissions
    permissions.forEach(function (item) {
        // do it if permission is match with key
        if (allPermissions[item])
            // assign hasPermission to true
            hasPermission = true;
    });

    // return has permissions
    return hasPermission;
}
