<?php

use PHPUnit\Framework\TestCase;

/* covers the anti-spam token in 'lib/functions.php' (`spamToken` / `spamTokenValid`) -- the timing/signature layer
   added alongside NNF's existing honeypot field, see 'HISTORY.txt' */
final class SpamProtectionTest extends TestCase {

    public function testTokenHasTheExpectedShape (): void {
        $this->assertMatchesRegularExpression ('/^[0-9]+\.[0-9a-f]{64}$/', spamToken ());
    }

    public function testFreshTokenIsRejectedByDefault (): void {
        //a bot that submits the instant it loads the page should be rejected by the default minimum age
        $this->assertFalse (spamTokenValid (spamToken ()));
    }

    public function testFreshTokenIsAcceptedWhenMinAgeIsZero (): void {
        $this->assertTrue (spamTokenValid (spamToken (), 0));
    }

    public function testTamperedSignatureIsRejected (): void {
        $token = spamToken ();
        list ($time, $sig) = explode ('.', $token);
        //flip the first hex character of the signature
        $sig[0] = $sig[0] === 'a' ? 'b' : 'a';
        $this->assertFalse (spamTokenValid ("$time.$sig", 0));
    }

    public function testTamperedTimestampIsRejected (): void {
        $token = spamToken ();
        list ($time, $sig) = explode ('.', $token);
        //move the (otherwise correctly-signed) timestamp: the signature no longer matches this timestamp
        $this->assertFalse (spamTokenValid ((((int) $time) + 100).".$sig", 0));
    }

    /** @dataProvider malformedTokenProvider */
    public function testMalformedTokensAreRejected ($token): void {
        $this->assertFalse (spamTokenValid ($token, 0));
    }

    public static function malformedTokenProvider (): array {
        return array (
            'null'             => array (null),
            'empty string'     => array (''),
            'no signature'     => array ('123456'),
            'non-numeric time' => array ('abc.'.str_repeat ('a', 64)),
            'short signature'  => array ('123456.abcd'),
            'garbage'          => array ('not a token at all'),
        );
    }

    public function testTokenOlderThanMaxAgeIsRejected (): void {
        $token = $this->tokenAgedSeconds (100);
        //default $min_age (3s) is satisfied; a $max_age of 50s is not
        $this->assertFalse (spamTokenValid ($token, 3, 50));
    }

    public function testTokenWithinMaxAgeIsAccepted (): void {
        $token = $this->tokenAgedSeconds (100);
        $this->assertTrue (spamTokenValid ($token, 3, 200));
    }

    //build a validly-signed token as though it had been issued `$seconds` ago, to test the age boundaries without
    //needing to actually sleep in the test suite. mirrors exactly what `spamToken ()` does internally
    private function tokenAgedSeconds (int $seconds): string {
        //ensure the secret has been generated at least once before we read it back
        spamToken ();

        $time   = time () - $seconds;
        $secret = trim ((string) file_get_contents (
                FORUM_ROOT.DIRECTORY_SEPARATOR.FORUM_USERS.DIRECTORY_SEPARATOR.'.spam_secret'
        ));
        return $time.'.'.hash_hmac ('sha256', (string) $time, $secret);
    }
}
