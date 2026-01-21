import { usePage } from '@inertiajs/vue3'

export function can(permission) {
    const page = usePage()
    const permissions = page.props.auth?.permissions || []

    return permissions.includes(permission)
}
