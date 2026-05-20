<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Services;

use App\Models\Tenant\Interview;
use Carbon\CarbonImmutable;

/**
 * Generates RFC 5545 (.ics) payloads for interview invitations. This is a
 * stop-gap so the API can emit a downloadable invite today. Direct Google
 * Calendar / Microsoft Graph OAuth integration is tracked separately and
 * will live in a future iteration of this service.
 */
class CalendarSyncService
{
    /**
     * @return string  Full ICS document, safe to write to disk or stream as
     *                 `text/calendar`.
     */
    public function buildInvite(Interview $interview): string
    {
        $interview->loadMissing(['application', 'interviewers']);

        $start = CarbonImmutable::parse($interview->scheduled_at)->setTimezone('UTC');
        $end   = $start->addMinutes((int) ($interview->duration_minutes ?? 60));

        $title       = $this->escape($interview->title ?: 'Interview');
        $applicant   = $this->escape($interview->application?->applicant_name ?? 'Candidate');
        $location    = $this->escape($interview->location ?? '');
        $description = $this->escape(sprintf(
            'Interview with %s (round: %s, mode: %s).%s',
            $applicant,
            $interview->round ?? 'general',
            $interview->mode ?? 'onsite',
            $interview->notes ? "\\n\\nNotes: " . $this->escape($interview->notes) : '',
        ));

        $organizer = 'mailto:hr@' . parse_url(config('app.url'), PHP_URL_HOST) ?: 'hr@example.com';
        $uid       = $interview->id . '@' . (parse_url(config('app.url'), PHP_URL_HOST) ?: 'erp.local');

        $attendees = $interview->interviewers
            ->filter(fn ($e) => filled($e->email))
            ->map(fn ($e) => "ATTENDEE;CN={$this->escape($e->first_name . ' ' . $e->last_name)};RSVP=TRUE:mailto:{$e->email}")
            ->push("ATTENDEE;CN={$applicant};RSVP=TRUE:mailto:{$this->escape($interview->application?->applicant_email ?? 'candidate@example.com')}")
            ->all();

        return implode("\r\n", array_filter(array_merge(
            [
                'BEGIN:VCALENDAR',
                'VERSION:2.0',
                'PRODID:-//Enterprise ERP//Interview Invite//EN',
                'METHOD:REQUEST',
                'BEGIN:VEVENT',
                'UID:' . $uid,
                'DTSTAMP:' . CarbonImmutable::now('UTC')->format('Ymd\THis\Z'),
                'DTSTART:' . $start->format('Ymd\THis\Z'),
                'DTEND:'   . $end->format('Ymd\THis\Z'),
                'SUMMARY:' . $title,
                'DESCRIPTION:' . $description,
                $location !== '' ? 'LOCATION:' . $location : null,
                'ORGANIZER:' . $organizer,
            ],
            $attendees,
            [
                'STATUS:CONFIRMED',
                'SEQUENCE:0',
                'END:VEVENT',
                'END:VCALENDAR',
            ]
        ))) . "\r\n";
    }

    /**
     * RFC 5545 §3.3.11 — escape commas, semicolons, backslashes; convert
     * newlines into the literal "\n" escape sequence.
     */
    private function escape(string $text): string
    {
        $replacements = [
            '\\' => '\\\\',
            ','  => '\,',
            ';'  => '\;',
            "\r\n" => '\n',
            "\n"   => '\n',
        ];
        return strtr($text, $replacements);
    }
}
