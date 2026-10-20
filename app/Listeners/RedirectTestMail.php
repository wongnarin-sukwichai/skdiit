<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * Testing phase: email to a test account (config demo.mail_domain, e.g. editor@skdiit.test) goes to
 * config demo.mail_to instead, and the subject starts with who it was for, e.g.
 * "[ถึง editor@skdiit.test · บรรณาธิการ] ...". Real addresses are untouched. Remove with the demo accounts.
 */
class RedirectTestMail
{
    private const ROLE_TH = [
        'admin' => 'ผู้ดูแลระบบ',
        'editor' => 'บรรณาธิการ',
        'reviewer' => 'ผู้ประเมิน',
        'journal' => 'Journal',
        'author' => 'ผู้ส่งผลงาน',
        'attendee' => 'ผู้เข้าร่วมงาน',
    ];

    public function handle(MessageSending $event): ?bool
    {
        $domain = config('demo.mail_domain');
        if (! config('demo.enabled') || ! $domain) {
            return null;
        }

        $message = $event->message;
        $isTest = fn (Address $address) => str_ends_with(strtolower($address->getAddress()), '@'.$domain);
        $test = array_filter($message->getTo(), $isTest);
        if (! $test) {
            return null;
        }

        $real = array_values(array_filter($message->getTo(), fn (Address $address) => ! $isTest($address)));
        $to = config('demo.mail_to');
        if (! $to && ! $real) {
            return false; // nobody real to send to
        }

        $labels = collect($test)->map(function (Address $address) {
            $role = User::where('email', $address->getAddress())->first()?->accessRole();

            return $address->getAddress().($role ? ' · '.(self::ROLE_TH[$role] ?? $role) : '');
        })->implode(', ');

        $message->to(...[...$real, ...($to ? [new Address($to)] : [])]);
        $message->subject("[ถึง {$labels}] ".$message->getSubject());

        return null;
    }
}
