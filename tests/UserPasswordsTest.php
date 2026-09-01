<?php

use PHPUnit\Framework\TestCase;

/* covers `userCreate` / `userVerify` in 'lib/functions.php' -- how the "users" folder stores password hashes, and
   the transparent upgrade of the pre-`password_hash ()` sha512 format, see 'HISTORY.txt' */
final class UserPasswordsTest extends TestCase {

    private string $file;

    protected function setUp (): void {
        $this->file = FORUM_DATA.DIRECTORY_SEPARATOR.FORUM_USERS.DIRECTORY_SEPARATOR
                    . 'pwtest_'.getmypid ().'_'.uniqid ().'.txt';
    }

    protected function tearDown (): void {
        @unlink ($this->file);
    }

    public function testUnknownNameVerifiesAsNull (): void {
        $this->assertNull (userVerify ($this->file, 'whatever', 'whatever'));
    }

    public function testCreatedPasswordVerifies (): void {
        $this->assertTrue (userCreate ($this->file, 'correct horse'));

        $stored = file_get_contents ($this->file);
        $this->assertSame ('$', $stored[0], 'stored value should be a password_hash() string');
        $this->assertStringNotContainsString ('correct horse', $stored);

        $this->assertTrue  (userVerify ($this->file, 'correct horse', 'irrelevant-legacy-input'));
        $this->assertFalse (userVerify ($this->file, 'wrong',         'irrelevant-legacy-input'));
    }

    public function testLegacySha512FileIsAcceptedAndUpgradedInPlace (): void {
        //what old NNF wrote: hash('sha512', <name-hash> . <password>)
        $nameHash = hash ('sha512', strtolower ('Alice'));
        $legacy   = $nameHash.'hunter2';
        file_put_contents ($this->file, hash ('sha512', $legacy));

        $this->assertTrue (userVerify ($this->file, 'hunter2', $legacy));

        //the file must now hold a modern hash, and still verify
        $this->assertSame ('$', file_get_contents ($this->file)[0]);
        $this->assertTrue (userVerify ($this->file, 'hunter2', $legacy));
    }

    public function testWrongPasswordAgainstLegacyFileFailsAndLeavesItAlone (): void {
        $legacy = hash ('sha512', strtolower ('bob')).'realpw';
        $sha    = hash ('sha512', $legacy);
        file_put_contents ($this->file, $sha);

        $this->assertFalse (userVerify ($this->file, 'guessed', $legacy));
        $this->assertSame  ($sha, file_get_contents ($this->file), 'a failed attempt must not rewrite the file');
    }

    public function testArgon2idWhenAvailable (): void {
        if (!defined ('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped ('this PHP build has no Argon2 support');
        }
        $file = $this->file;
        file_put_contents ($file, password_hash ('secret', PASSWORD_ARGON2ID));
        $this->assertStringStartsWith ('$argon2id$', file_get_contents ($file));
        $this->assertTrue  (userVerify ($file, 'secret', 'x'));
        $this->assertFalse (userVerify ($file, 'nope',   'x'));
    }
}
