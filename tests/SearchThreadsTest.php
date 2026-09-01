<?php

use PHPUnit\Framework\TestCase;

/* covers `searchThreads ()` in 'lib/functions.php'. It reads real ".rss" files from disk under `FORUM_ROOT`, so this
   test drops a throwaway, uniquely-named ".rss" fixture directly into the (real) repo root before each test and
   removes it again afterwards -- `FORUM_ROOT` is a `define ()`d constant set once for the whole test run (see
   'tests/bootstrap.php') and so can't be pointed at a scratch directory per test the way e.g. `FORUM_USERS` is. */
final class SearchThreadsTest extends TestCase {

    private string $fixtureFile;
    private string $nonce;

    public static function setUpBeforeClass (): void {
        //`searchThreads ()` builds each result's link via `url ()`, which needs these routing constants (normally
        //set up per-request by 'start.php'); a simple "site at the domain root, HTAccess on" scenario is enough here
        foreach (array ('FORUM_PATH' => '/', 'HTACCESS' => true, 'PATH' => '', 'PATH_URL' => '') as $const => $value) {
            if (!defined ($const)) define ($const, $value);
        }
    }

    protected function setUp (): void {
        $this->nonce       = 'zzzsearchnonce'.getmypid ();
        $this->fixtureFile = FORUM_ROOT.DIRECTORY_SEPARATOR.'.nnf_test_'.$this->nonce.'.rss';
    }

    protected function tearDown (): void {
        @unlink ($this->fixtureFile);
    }

    private function writeFixture (string $item1, string $item2 = ''): void {
        file_put_contents ($this->fixtureFile, '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rss version="2.0"><channel><title>Test</title><link>http://example.test</link>'
            .$item1.$item2
            .'</channel></rss>'
        );
    }

    private function item (string $title, string $description, string $author = 'alice', string $id = 'abc123'): string {
        return '<item><title>'.$title.'</title>'
            .'<link>http://example.test/thread#'.$id.'</link>'
            .'<author>'.$author.'</author>'
            .'<pubDate>Mon, 01 Jan 2024 12:00:00 +0000</pubDate>'
            .'<description>'.$description.'</description></item>';
    }

    public function testFindsAPostByTitle (): void {
        $this->writeFixture ($this->item ("Hello {$this->nonce} World", 'irrelevant text'));

        $results = searchThreads ($this->nonce);

        $this->assertCount (1, $results);
        $this->assertSame ("Hello {$this->nonce} World", $results[0]['title']);
    }

    public function testFindsAPostByMessageText (): void {
        $this->writeFixture ($this->item ('Some Title', "the message body mentions {$this->nonce} here"));

        $results = searchThreads ($this->nonce);

        $this->assertCount (1, $results);
    }

    public function testFindsAPostByAuthor (): void {
        $this->writeFixture ($this->item ('Some Title', 'body text', "author{$this->nonce}"));

        $results = searchThreads ($this->nonce);

        $this->assertCount (1, $results);
        $this->assertSame ("author{$this->nonce}", $results[0]['author']);
    }

    public function testSearchIsCaseInsensitive (): void {
        $this->writeFixture ($this->item (strtoupper ($this->nonce), 'body'));

        $this->assertCount (1, searchThreads (strtolower ($this->nonce)));
    }

    public function testMultiWordQueryRequiresAllTermsToMatch (): void {
        $this->writeFixture (
            $this->item ("{$this->nonce}-alpha", 'body one', 'alice', 'id1'),
            $this->item ("{$this->nonce}-beta",  'body two', 'alice', 'id2')
        );

        //only the second item contains both words
        $results = searchThreads ("{$this->nonce}-beta body");

        $this->assertCount (1, $results);
        $this->assertSame ("{$this->nonce}-beta", $results[0]['title']);
    }

    public function testNoMatchReturnsEmptyArray (): void {
        $this->writeFixture ($this->item ('Something else entirely', 'nothing relevant'));

        $this->assertSame (array (), searchThreads ($this->nonce));
    }

    public function testBlankQueryReturnsEmptyArrayWithoutScanningAnything (): void {
        $this->assertSame (array (), searchThreads (''));
        $this->assertSame (array (), searchThreads ('   '));
    }

    public function testSnippetIsPlainTextNotHtml (): void {
        //`description` fields store already-HTML-formatted post bodies (see `formatText ()`) as XML-escaped text
        //(NNF writes them with `DOMTemplate::set ()`'s default, non-"asHTML" mode) -- not as real child elements
        $this->writeFixture ($this->item ("Title {$this->nonce}", '&lt;p&gt;a &lt;strong&gt;bold&lt;/strong&gt; word&lt;/p&gt;'));

        $results = searchThreads ($this->nonce);

        $this->assertStringNotContainsString ('<', $results[0]['snippet']);
        $this->assertStringContainsString ('a bold word', $results[0]['snippet']);
    }

    public function testLinkContainsThePostIdFragment (): void {
        $this->writeFixture ($this->item ("Title {$this->nonce}", 'body', 'alice', 'thepostid'));

        $results = searchThreads ($this->nonce);

        $this->assertStringEndsWith ('#thepostid', $results[0]['link']);
    }

    public function testResultsAreSortedNewestFirst (): void {
        $older = '<item><title>'."{$this->nonce}-older".'</title><link>http://example.test/t#a</link>'
            .'<author>alice</author><pubDate>Mon, 01 Jan 2024 12:00:00 +0000</pubDate><description>x</description></item>';
        $newer = '<item><title>'."{$this->nonce}-newer".'</title><link>http://example.test/t#b</link>'
            .'<author>alice</author><pubDate>Wed, 01 Jan 2025 12:00:00 +0000</pubDate><description>x</description></item>';
        $this->writeFixture ($older, $newer);

        $results = searchThreads ($this->nonce);

        $this->assertCount (2, $results);
        $this->assertSame ("{$this->nonce}-newer", $results[0]['title']);
        $this->assertSame ("{$this->nonce}-older", $results[1]['title']);
    }
}
