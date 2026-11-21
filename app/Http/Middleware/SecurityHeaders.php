<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Basic browser hardening headers.
 *
 * The microphone stays allowed for this site (speaking practice: speech recognition and
 * recording with MediaRecorder); the camera and the location are never needed.
 *
 * No Content-Security-Policy is sent, like in the other projects with this stack. The app
 * embeds YouTube videos (https://www.youtube-nocookie.com), loads word pictures from
 * https://en.wikipedia.org (fetch) and https://upload.wikimedia.org (images) and plays
 * its own recordings from blob: URLs, so a policy added later must allow those.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // Who may frame this app, not what it may embed: the YouTube iframes keep working.
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        // Embedded YouTube players need the origin of the page as referrer.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(self), geolocation=()');

        return $response;
    }
}
