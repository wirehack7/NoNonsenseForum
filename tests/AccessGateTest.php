<?php

use PHPUnit\Framework\TestCase;

/* covers the optional access gate in 'lib/functions.php' (`accessPasswords` / `accessMatch` / `accessCookie` /
   `accessGranted`) -- the shared "door" password(s) that can sit in front of the whole forum, see 'HISTORY.txt' */
final class AccessGateTest extends TestCase {

    private string $file;

    protected function setUp (): void {
        $this->file = FORUM_DATA.DIRECTORY_SEPARATOR.FORUM_USERS.DIRECTORY_SEPARATOR.'access.txt';
    }

    protected function tearDown (): void {
        @unlink ($this->file);
    }

    private function writeAccess (string $contents): void {
        file_put_contents ($this->file, $contents);
    }

    public function testGateIsOffWhenFileMissing (): void {
        $this->assertSame (array (), accessPasswords ());
    }

    public function testPasswordsAreParsedIgnoringBlanksCommentsAndWhitespace (): void {
        $this->writeAccess ("hunter2\n\n  spaced-out  \n# a comment\nletmein\n");
        $this->assertSame (array ('hunter2', 'spaced-out', 'letmein'), accessPasswords ());
    }

    public function testTrailingLabelIsStrippedButInlineHashIsKept (): void {
        $this->writeAccess (
            "\$2y\$10\$abcdefghijklmnopqrstuv  # Alice, 2026-09-01\n" .
            "pa#ss\n" .                      //- '#' with no leading space is part of the password
            "plain   #   the meetup\n"
        );
        $this->assertSame (
            array ('$2y$10$abcdefghijklmnopqrstuv', 'pa#ss', 'plain'),
            accessPasswords ()
        );
    }

    public function testAnyOnePlaintextPasswordMatches (): void {
        $pw = array ('alpha', 'bravo', 'charlie');
        $this->assertSame ('bravo', accessMatch ($pw, 'bravo'));
        $this->assertSame ('charlie', accessMatch ($pw, 'charlie'));
    }

    public function testWrongPasswordDoesNotMatch (): void {
        $this->assertSame ('', accessMatch (array ('alpha', 'bravo'), 'delta'));
        $this->assertSame ('', accessMatch (array ('alpha'), ''));
    }

    public function testHashedPasswordLineMatches (): void {
        $hash = password_hash ('s3cret', PASSWORD_DEFAULT);
        $this->assertSame ($hash, accessMatch (array ($hash), 's3cret'));
        $this->assertSame ('',    accessMatch (array ($hash), 'nope'));
    }

    public function testCookieRoundTripsForAValidPassword (): void {
        $pw     = array ('alpha', 'bravo');
        $cookie = accessCookie (accessMatch ($pw, 'bravo'));
        $this->assertTrue  (accessGranted ($pw, $cookie));
        $this->assertFalse (accessGranted ($pw, 'forged-cookie-value'));
        $this->assertFalse (accessGranted ($pw, ''));
    }

    public function testRemovingAPasswordInvalidatesItsCookie (): void {
        $cookie = accessCookie ('bravo');
        $this->assertTrue  (accessGranted (array ('alpha', 'bravo'), $cookie));  //- still listed
        $this->assertFalse (accessGranted (array ('alpha'),          $cookie));  //- 'bravo' revoked
    }
}
