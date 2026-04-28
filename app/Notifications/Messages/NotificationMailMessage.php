<?php

declare(strict_types=1);

namespace App\Notifications\Messages;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * MailMessage variant that supports small subcopy-styled footer lines (e.g. unsubscribe + email preferences) rendered
 * below the main body via the customised resources/views/vendor/notifications/email.blade.php template.
 */
final class NotificationMailMessage extends MailMessage
{
    /**
     * Subcopy-styled lines rendered below the main body.
     *
     * @var list<string>
     */
    public array $footerLines = [];

    /**
     * Append one or more small subcopy lines to the email footer.
     *
     * @param  string|list<string>  $lines
     */
    public function footer(string|array $lines): static
    {
        foreach ((array) $lines as $line) {
            $this->footerLines[] = $line;
        }

        return $this;
    }

    /**
     * Expose footerLines to the email view alongside the parent's keys.
     *
     * @return array<string, mixed>
     */
    public function toArray()
    {
        /** @var array<string, mixed> $parent */
        $parent = parent::toArray();

        return array_merge($parent, [
            'footerLines' => $this->footerLines,
        ]);
    }
}
