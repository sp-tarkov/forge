<x-layouts::static-toc>
    <x-slot name="pageTitle">{{ __('SPTBot Privacy Policy') }}</x-slot>

    <x-slot name="pageDescription">{{ __('Privacy Policy for SPTBot Discord Bot') }}</x-slot>

    <x-slot name="tableOfContents">
        <x-table-of-contents-item
            href="#what-bot-does"
            title="What the Bot Does"
        />
        <x-table-of-contents-item
            href="#data-processed-transiently"
            title="Data Processed Transiently"
        />
        <x-table-of-contents-item
            href="#data-stored-by-bot"
            title="Data Stored by the Bot"
        />
        <x-table-of-contents-item
            href="#data-stored-within-discord"
            title="Data Stored Within Discord"
        />
        <x-table-of-contents-item
            href="#operational-logs"
            title="Operational Logs"
        />
        <x-table-of-contents-item
            href="#data-sharing"
            title="Data Sharing"
        />
        <x-table-of-contents-item
            href="#your-choices"
            title="Your Choices"
        />
        <x-table-of-contents-item
            href="#changes-policy"
            title="Changes to This Policy"
        />
        <x-table-of-contents-item
            href="#contact"
            title="Contact"
        />
    </x-slot>

    <p><strong>Effective Date:</strong> July 28, 2026</p>

    <p>SPTBot is a private Discord bot operated by the staff of the Single Player Tarkov (SPT) community server. It runs only in that server: it is configured with a single guild ID and automatically leaves any other server it is added to. This policy describes what data the bot processes, what it stores, and how that data is used.</p>

    <h2 id="what-bot-does">1. What the Bot Does</h2>
    <p>SPTBot provides automated moderation (spam, scam-link, and disallowed-attachment detection), a member verification flow, technical support thread triage, and moderator tooling for the SPT server.</p>

    <h2 id="data-processed-transiently">2. Data Processed Transiently</h2>
    <p>To perform automated moderation and support triage, the bot reads messages posted in the SPT server, including their text, attachments, and embedded links. This content is inspected in memory as messages arrive and is then discarded. The bot does not build profiles from message content, does not share it with any third party, and does not use it to train machine learning or AI models.</p>

    <h2 id="data-stored-by-bot">3. Data Stored by the Bot</h2>
    <p>The bot stores a minimal set of records in a private database on a server controlled by SPT staff. No third party has access to it.</p>
    <ul>
        <li><strong>Moderation reports:</strong> when a user reports a message or user through the bot's report commands, the bot stores the reporter's user ID, the reported user and message IDs, a link to the message, the reported message's text, and the reason given. This preserves evidence for moderators even if the offending message is deleted.</li>
        <li><strong>Spam-detection cache:</strong> for each user's recent link- or attachment-bearing posts, the bot stores the user ID, channel ID, timestamp, attachment count, the hostnames of posted links, and hashed fingerprints of attachments. Full message text is not stored. Entries are continuously pruned to a short detection window and capped in size.</li>
        <li><strong>Verification state:</strong> the number of failed verification attempts per user ID. Answers submitted in the verification application are reviewed by staff within Discord and are not stored in the database.</li>
        <li><strong>Support thread state:</strong> the thread ID, thread author's user ID, timestamps, and flags for which required log files have been provided.</li>
        <li><strong>Attachment blocklist:</strong> for attachments a moderator has blocked, the file's name, size, content hash, a link to the source message, and the blocking moderator's user ID.</li>
    </ul>
    <p>The bot also stores cached data from the public Forge mod API; this contains no user data.</p>

    <h2 id="data-stored-within-discord">4. Data Stored Within Discord</h2>
    <p>Some moderation features repost content into private, staff-only channels on Discord itself: deleted messages are logged for abuse review, and reports and verification applications are posted for staff handling. This data remains on Discord and is covered by <a href="https://discord.com/privacy" target="_blank" rel="noopener noreferrer">Discord's own privacy policy</a>.</p>

    <h2 id="operational-logs">5. Operational Logs</h2>
    <p>The bot writes technical logs (events, errors, and related IDs) to daily files kept for up to 50 days, after which they are deleted automatically.</p>

    <h2 id="data-sharing">6. Data Sharing</h2>
    <p>Stored data is used solely for moderating and operating the SPT server. It is never sold, shared with third parties, or used for advertising, analytics, or model training.</p>

    <h2 id="your-choices">7. Your Choices</h2>
    <p>Automated moderation applies to all messages posted in the SPT server and cannot be disabled per user; if you do not want your messages scanned, please do not post in the server. To ask what data the bot holds about you or to request its deletion, contact the server's moderation team. Requests are honoured unless the record is needed for an active moderation matter.</p>

    <h2 id="changes-policy">8. Changes to This Policy</h2>
    <p>If the bot's data handling changes, this document will be updated and the effective date revised.</p>

    <h2 id="contact">9. Contact</h2>
    <p>Questions about this policy can be directed to the SPT server's staff team via the server's support channels.</p>
</x-layouts::static-toc>
