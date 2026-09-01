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
        $this->assertNull (userVerify ($this->file, 'whatever', hash ('sha512', 'whoever')));
    }

    public function testCreatedPasswordVerifies (): void {
        $this->assertTrue (userCreate ($this->file, 'correct horse'));

        $stored = file_get_contents ($this->file);
        $this->assertSame ('$', $stored[0], 'stored value should be a password_hash() string');
        $this->assertStringNotContainsString ('correct horse', $stored);

        $nameHash = hash ('sha512', 'ignored-for-modern-hashes');
        $this->assertTrue  (userVerify ($this->file, 'correct horse', $nameHash));
        $this->assertFalse (userVerify ($this->file, 'wrong',         $nameHash));
    }

    public function testLegacySha512FileIsAcceptedAndUpgradedInPlace (): void {
        //what old NNF wrote: hash('sha512', <name-hash> . <password>)
        $nameHash = hash ('sha512', strtolower ('Alice'));
        file_put_contents ($this->file, hash ('sha512', $nameHash.'hunter2'));

        $this->assertTrue (userVerify ($this->file, 'hunter2', $nameHash));

        //the file must now hold a modern hash, and still verify
        $this->assertSame ('$', file_get_contents ($this->file)[0]);
        $this->assertTrue (userVerify ($this->file, 'hunter2', $nameHash));
    }

    public function testWrongPasswordAgainstLegacyFileFailsAndLeavesItAlone (): void {
        $nameHash = hash ('sha512', strtolower ('bob'));
        $sha      = hash ('sha512', $nameHash.'realpw');
        file_put_contents ($this->file, $sha);

        $this->assertFalse (userVerify ($this->file, 'guessed', $nameHash));
        $this->assertSame  ($sha, file_get_contents ($this->file), 'a failed attempt must not rewrite the file');
    }

    public function testArgon2idWhenAvailable (): void {
        if (!defined ('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped ('this PHP build has no Argon2 support');
        }
        $file = $this->file;
        file_put_contents ($file, password_hash ('secret', PASSWORD_ARGON2ID));
        $this->assertStringStartsWith ('$argon2id$', file_get_contents ($file));
        $nameHash = hash ('sha512', 'ignored');
        $this->assertTrue  (userVerify ($file, 'secret', $nameHash));
        $this->assertFalse (userVerify ($file, 'nope',   $nameHash));
    }
}
