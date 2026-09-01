<?php

use PHPUnit\Framework\TestCase;

/* covers the input/output sanitisation helpers in 'lib/utf8safe.php' */
final class Utf8SafeTest extends TestCase {

    public function testSafeHtmlEncodesAngleBracketsAndAmpersands (): void {
        $this->assertSame (
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            safeHTML ('<script>alert(1)</script>')
        );
    }

    public function testSafeHtmlDoesNotEncodeQuotes (): void {
        //`safeHTML` uses ENT_NOQUOTES -- quotes are left alone as NNF's markup / HTML fragments rely on this
        $this->assertSame ('say "hi"', safeHTML ('say "hi"'));
    }

    public function testSafeUrlEncodesSpacesButKeepsSlashes (): void {
        $this->assertSame ('a%20b/c%20d', safeURL ('a b/c d'));
    }

    public function testSafeUrlEncodesQuestionMarksAndAmpersands (): void {
        $this->assertSame ('a%3Fb%26c', safeURL ('a?b&c'));
    }

    public function testSafeTrimRemovesLeadingAndTrailingWhitespace (): void {
        $text = "  \t hello world \n ";
        $this->assertSame ('hello world', safeTrim ($text));
    }

    public function testSafeTrimRemovesUnicodeSpaces (): void {
        //U+00A0 NO-BREAK SPACE, U+2003 EM SPACE -- not covered by PHP's own `trim ()`
        $text = "\u{00A0}\u{2003}hello\u{2003}\u{00A0}";
        $this->assertSame ('hello', safeTrim ($text));
    }

    public function testSafeTransliterateFlattensToLowercaseDashedAscii (): void {
        $this->assertSame ('hello-world', safeTransliterate ('Hello World'));
    }

    public function testSafeTransliterateCollapsesPunctuationToASingleDash (): void {
        $this->assertSame ('what-is-this', safeTransliterate ('What?! Is -- this...'));
    }

    public function testSafeTransliterateTrimsDashesFromBothEnds (): void {
        $this->assertSame ('trimmed', safeTransliterate ('  trimmed!!  '));
    }

    public function testSafeTransliterateNeverReturnsAnEmptyString (): void {
        //a title made entirely of characters that flatten away would otherwise produce an unusable filename
        $this->assertSame ('_', safeTransliterate ('???'));
    }
}
