<?php

namespace App;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The clock a task deadline is measured against.
 *
 * A deadline is a wall clock reading rather than an instant. The form's
 * `datetime-local` input has no zone to send, and the column keeps exactly the
 * digits that were picked: "18:00" means six in the evening where the person
 * who typed it is, and the application never learns more than that.
 *
 * So "has this deadline passed" can only be answered on that person's own
 * clock. `config('app.timezone')` is the wrong one to ask — for a visitor at
 * UTC-3 it already reads three hours ahead, which rejects deadlines that are
 * still hours away and lists tasks as overdue before they are.
 */
final class DeadlineClock
{
    /**
     * The cookie the browser's IANA timezone arrives in.
     *
     * Written by the inline script in `resources/views/app.blade.php`, and left
     * out of cookie encryption in `bootstrap/app.php` so that what JavaScript
     * writes is what is read back here.
     */
    public const TIMEZONE_COOKIE = 'interface_timezone';

    /**
     * The timezone the interface is being read in.
     *
     * The cookie is written by the browser, which makes it visitor input like
     * any form field, so a value PHP does not itself recognise as a zone is
     * discarded rather than trusted. The application's own timezone stands in
     * whenever there is no usable cookie: the first document load of a browser
     * that has never been here, and clients with no interface at all, such as
     * the API.
     */
    public static function timezone(Request $request): string
    {
        $candidate = $request->cookie(self::TIMEZONE_COOKIE);

        return is_string($candidate)
            && in_array($candidate, timezone_identifiers_list(), true)
                ? $candidate
                : (string) config('app.timezone');
    }

    /**
     * The current wall clock reading of the interface, carrying the
     * application's timezone.
     *
     * Relabelling the zone rather than converting to it is the point: the
     * digits are what a stored `due_at` holds, so a value from here compares
     * against one straight out of the database — in a query, in a validation
     * rule, in a `diff` — without either side being shifted.
     */
    public static function now(Request $request): Carbon
    {
        return Carbon::now(self::timezone($request))
            ->shiftTimezone((string) config('app.timezone'));
    }
}
