<?php

declare(strict_types=1);

describe('static pages', function (): void {
    it('renders the community standards page', function (): void {
        $this->get('/community-standards')->assertOk();
    });

    it('renders the content guidelines page', function (): void {
        $this->get('/content-guidelines')->assertOk();
    });

    it('renders the contact page', function (): void {
        $this->get('/contact')->assertOk();
    });

    it('renders the privacy policy page', function (): void {
        $this->get('/privacy-policy')->assertOk();
    });

    it('renders the terms of service page', function (): void {
        $this->get('/terms-of-service')->assertOk();
    });

    it('renders the DMCA page', function (): void {
        $this->get('/dmca')->assertOk();
    });

    it('renders the installer page', function (): void {
        $this->get('/installer')->assertOk();
    });

    it('renders the banned user page', function (): void {
        $this->get('/user-banned')->assertOk();
    });
});
