<?php

use PHPUnit\Framework\TestCase;

/* covers the optional access gate in 'lib/functions.php' -- the shared "door" password(s) that can sit in front of
   the whole forum or an individual sub-forum ('access.txt'), see 'HISTORY.txt' */
final class AccessGateTest extends TestCase {

    private string $root;
    private string $sub;

    public static function setUpBeforeClass (): void {
        //`accessGate ()` walks up from the current sub-forum; pretend we're viewing "/sub/"
        if (!defined ('PATH_DIR')) define ('PATH_DIR', DIRECTORY_SEPARATOR.'sub'.DIRECTORY_SEPARATOR);
    }

    protected function setUp (): void {
        $this->root = FORUM_DATA.DIRECTORY_SEPARATOR.'access.txt';
        $this->sub  = FORUM_DATA.DIRECTORY_SEPARATOR.'sub'.DIRECTORY_SEPARATOR.'access.txt';
        @mkdir (dirname ($this->sub), 0777, true);
    }

    protected function tearDown (): void {
        @unlink ($this->root);
        @unlink ($this->sub);
    }

    /* --- parsing ------------------------------------------------------------------------------------------------ */

    public function testMissingFileYieldsNoPasswords (): void {
        $this->assertSame (array (), accessFileEntries ($this->root));
    }

    public function testEntriesParsedIgnoringBlanksCommentsAndLabels (): void {
        file_put_contents ($this->root,
            "hunter2\n" .
            "\n" .
            "  spaced-out  \n" .
            "# a whole-line comment\n" .
            "\$2y\$10\$abcdefghijklmnopqrstuv  # Alice, 2026-09-01\n" .
            "pa#ss\n"                        //- '#' with no leading space stays part of the password
        );
        $this->assertSame (
            array ('hunter2', 'spaced-out', '$2y$10$abcdefghijklmnopqrstuv', 'pa#ss'),
            accessFileEntries ($this->root)
        );
    }

    /* --- matching ----------------------------------------------------------------------------------------------- */

    public function testAnyOnePlaintextPasswordMatches (): void {
        $pw = array ('alpha', 'bravo', 'charlie');
        $this->assertSame ('bravo',   accessMatch ($pw, 'bravo'));
        $this->assertSame ('charlie', accessMatch ($pw, 'charlie'));
        $this->assertSame ('',        accessMatch ($pw, 'delta'));
        $this->assertSame ('',        accessMatch ($pw, ''));
    }

    public function testHashedPasswordLineMatches (): void {
        $hash = password_hash ('s3cret', PASSWORD_DEFAULT);
        $this->assertSame ($hash, accessMatch (array ($hash), 's3cret'));
        $this->assertSame ('',    accessMatch (array ($hash), 'nope'));
    }

    /* --- cookies ------------------------------------------------------------------------------------------------- */

    public function testCookieRoundTripsAndCantBeForged (): void {
        $pw     = array ('alpha', 'bravo');
        $cookie = accessCookie (accessMatch ($pw, 'bravo'));
        $this->assertTrue  (accessGranted ($pw, $cookie));
        $this->assertFalse (accessGranted ($pw, 'forged'));
        $this->assertFalse (accessGranted ($pw, ''));
    }

    public function testRemovingAPasswordInvalidatesItsCookie (): void {
        $cookie = accessCookie ('bravo');
        $this->assertTrue  (accessGranted (array ('alpha', 'bravo'), $cookie));
        $this->assertFalse (accessGranted (array ('alpha'),          $cookie));
    }

    public function testDifferentScopesGetDifferentCookieNames (): void {
        $this->assertNotSame (accessCookieName (''), accessCookieName ('sub'));
        $this->assertSame    (accessCookieName ('sub'), accessCookieName ('sub'));
    }

    /* --- scope resolution (accessGate walks up from PATH_DIR="/sub/") ------------------------------------------- */

    public function testNoGateWhenNoAccessFileAnywhere (): void {
        $this->assertNull (accessGate ());
    }

    public function testRootAccessFileGovernsASubForum (): void {
        file_put_contents ($this->root, "sitewide\n");
        $this->assertSame (array ('', array ('sitewide')), accessGate ());
    }

    public function testNearestAccessFileWins (): void {
        file_put_contents ($this->root, "sitewide\n");
        file_put_contents ($this->sub,  "sub-only\n");
        $this->assertSame (array ('sub', array ('sub-only')), accessGate ());
    }
}
