<?php

use PHPUnit\Framework\TestCase;

/* covers `url ()` in 'lib/functions.php', which builds either "pretty" (HTAccess) or "?file=…" (no-HTAccess) URLs.
   Each test needs a different combination of the routing constants that 'start.php' normally derives from the
   request (`FORUM_PATH`, `HTACCESS`, `PATH`, `PATH_URL`), so every test method here runs in its own process via
   `@runInSeparateProcess` -- these are `define ()`d constants and so can't be redefined between tests otherwise. */
final class UrlTest extends TestCase {

    /** @runInSeparateProcess */
    public function testPrettyUrlAtSiteRootForAThread (): void {
        define ('FORUM_PATH', '/');
        define ('HTACCESS',   true);
        define ('PATH',       '');
        define ('PATH_URL',   '');

        $this->assertSame ('/my-thread', url ('', 'my-thread'));
    }

    /** @runInSeparateProcess */
    public function testPrettyUrlWithAPageNumber (): void {
        define ('FORUM_PATH', '/');
        define ('HTACCESS',   true);
        define ('PATH',       '');
        define ('PATH_URL',   '');

        $this->assertSame ('/my-thread+2', url ('', 'my-thread', 2));
    }

    /** @runInSeparateProcess */
    public function testNoHtaccessUrlForAThread (): void {
        define ('FORUM_PATH', '/');
        define ('HTACCESS',   false);
        define ('PATH',       '');
        define ('PATH_URL',   '');

        $this->assertSame ('/thread.php?file=my-thread', url ('', 'my-thread'));
    }

    /** @runInSeparateProcess */
    public function testNoHtaccessUrlForASubForumIndex (): void {
        define ('FORUM_PATH', '/');
        define ('HTACCESS',   false);
        define ('PATH',       '');
        define ('PATH_URL',   '');

        $this->assertSame ('/index.php?path=sub/', url ('sub/'));
    }

    /** @runInSeparateProcess */
    public function testNoHtaccessUrlWithAnAppendAction (): void {
        define ('FORUM_PATH', '/');
        define ('HTACCESS',   false);
        define ('PATH',       '');
        define ('PATH_URL',   '');

        $this->assertSame (
            '/thread.php?append=abc123&file=my-thread',
            url ('', 'my-thread', 0, 'append', 'abc123')
        );
    }
}
