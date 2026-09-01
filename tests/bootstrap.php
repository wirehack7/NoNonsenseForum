<?php
/* PHPUnit bootstrap: defines just enough of NNF's usual request-time constants (normally set up by 'start.php' while
   processing a real HTTP request) so that the pure, testable functions in 'lib/' can be exercised on their own.

   Constants that only a handful of tests need (e.g. `url ()`'s routing constants, which differ per scenario) are
   left to those tests to define themselves -- see 'UrlTest.php', which uses `@runInSeparateProcess` so each test
   method gets its own, uncontaminated set of constants. */

define ('FORUM_ROOT',  dirname (__DIR__));
define ('FORUM_LIB',   FORUM_ROOT.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR);
//a throwaway, private folder for the anti-spam secret (see 'spamSecret ()' in 'lib/functions.php') that tests
//generate. `FORUM_USERS` is always a path *relative* to `FORUM_ROOT` (see 'config.default.php'), same as in
//production, so this has to be a folder name, not an absolute path
define ('FORUM_USERS', '.nnf_test_users_'.getmypid ());
$GLOBALS['NNF_TEST_USERS_DIR'] = FORUM_ROOT.DIRECTORY_SEPARATOR.FORUM_USERS;
@mkdir ($GLOBALS['NNF_TEST_USERS_DIR'], 0777, true);
//clean up after ourselves once the test process ends
register_shutdown_function (function () {
        //`glob ('*')` skips dotfiles (like '.spam_secret') -- `scandir` doesn't
        foreach (array_diff (@scandir ($GLOBALS['NNF_TEST_USERS_DIR']) ?: array (), array ('.', '..')) as $file) {
                @unlink ($GLOBALS['NNF_TEST_USERS_DIR'].DIRECTORY_SEPARATOR.$file);
        }
        @rmdir ($GLOBALS['NNF_TEST_USERS_DIR']);
});
//`formatText ()` needs this to decide whether a detected link is internal (no "external" rel) or not
define ('FORUM_URL', 'http://forum.example.test');

require_once FORUM_LIB.'utf8safe.php';
require_once FORUM_LIB.'functions.php';
