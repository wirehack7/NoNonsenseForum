<?php

use PHPUnit\Framework\TestCase;

/* covers NNF's custom plain-text markup parser, `formatText ()` in 'lib/functions.php'. Only the single-argument
   form is exercised (no permalink / post-id / thread) so that these tests don't need thread / moderator globals set
   up -- see 'formatText ()' for what those extra arguments unlock ("@name" references, title self-links) */
final class FormatTextTest extends TestCase {

    public function testWrapsPlainTextInAParagraph (): void {
        $this->assertSame ("\n<p>\nhello\n</p>", formatText ('hello'));
    }

    public function testBoldKeepsTheAsterisksInsideTheTag (): void {
        $this->assertStringContainsString ('<strong>*bold*</strong>', formatText ('*bold*'));
    }

    public function testItalicKeepsTheUnderscoresInsideTheTag (): void {
        $this->assertStringContainsString ('<em>_italic_</em>', formatText ('_italic_'));
    }

    public function testInlineCode (): void {
        $this->assertStringContainsString ('<code>`code`</code>', formatText ('`code`'));
    }

    public function testDividerLine (): void {
        $this->assertStringContainsString ('<p class="hr">---</p>', formatText ("above\n\n---\n\nbelow"));
    }

    public function testHeadingProducesAnAnchoredH2 (): void {
        $html = formatText ('::My Heading');
        $this->assertMatchesRegularExpression ('/<h2 id="[^"]+">.*My Heading.*<\/h2>/', $html);
    }

    public function testBlockquote (): void {
        $html = formatText ('"a quoted paragraph"');
        $this->assertStringContainsString ('<blockquote>', $html);
        $this->assertStringContainsString ('a quoted paragraph', $html);
    }

    public function testAutoLinksAPlainUrl (): void {
        $html = formatText ('see http://example.com/page for details');
        $this->assertStringContainsString ('<a href="http://example.com/page"', $html);
    }

    public function testAutoLinkedExternalUrlGetsNofollowExternal (): void {
        //FORUM_URL (in 'tests/bootstrap.php') is "http://forum.example.test", so this is an external link
        $html = formatText ('http://example.com/page');
        $this->assertStringContainsString ('rel="nofollow external"', $html);
    }

    public function testAutoLinksAnEmailAddress (): void {
        $html = formatText ('contact me at test@example.com please');
        $this->assertStringContainsString ('<a href="mailto:test@example.com"', $html);
    }

    public function testTextIsHtmlEscapedBeforeMarkupIsApplied (): void {
        //markup processing must not itself become an XSS vector
        $html = formatText ('<script>alert(1)</script>');
        $this->assertStringNotContainsString ('<script>', $html);
        $this->assertStringContainsString ('&lt;script&gt;', $html);
    }
}
