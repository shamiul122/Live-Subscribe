/**
 * Reads a cookie value by name.
 *
 * The application does not render a CSRF meta tag, so the XSRF-TOKEN cookie
 * that Laravel sets is the only way a fetch-based request can send the CSRF
 * header required by the web middleware group.
 */
export function readCookie(name: string): string | null {
    const match = document.cookie.match(
        new RegExp('(^|;\\s*)' + name + '=([^;]*)'),
    );

    return match ? decodeURIComponent(match[2]) : null;
}

export function xsrfToken(): string {
    return readCookie('XSRF-TOKEN') ?? '';
}
