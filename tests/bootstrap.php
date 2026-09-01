<?php
/* PHPUnit bootstrap: defines just enough of NNF's usual request-time constants (normally set up by 'start.php' while
   processing a real HTTP request) so that the pure, testable functions in 'lib/' can be exercised on their own.

   Constants that only a handful of tests need (e.g. `url ()`'s routing constants, which differ per scenario) are
   left to those tests to define themselves -- see 'UrlTest.php', which uses `@runInSeparateProcess` so each test
   method gets its own, uncontaminated set of constants. */

define ('FORUM_ROOT',  dirname (__DIR__));
define ('FORUM_LIB',   FORUM_ROOT.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR);
//in production NNF's request-time data root is separable from its code via the `NNF_DATA_DIR` environment variable
//(see the `FORUM_DATA` constant in 'start.php'). the tests use that same seam to keep every file they touch -- the
//anti-spam secret, `searchThreads ()`'s ".rss" fixtures -- inside a single throwaway temp directory, so a test run
//never writes anything into the repo. `FORUM_USERS` stays a plain folder name, relative to `FORUM_DATA`, as always.
define ('FORUM_DATA',  sys_get_temp_dir ().DIRECTORY_SEPARATOR.'nnf_test_'.getmypid ());
define ('FORUM_USERS', 'users');
//password-hash algorithm for the "users" folder (see 'config.default.php'); the portable default is fine for tests
define ('FORUM_PASS_ALGO', PASSWORD_DEFAULT);
@mkdir (FORUM_DATA.DIRECTORY_SEPARATOR.FORUM_USERS, 0777, true);
//delete the whole temp data dir once the test process ends
register_shutdown_function (function () {
        $rrmdir = function ($dir) use (&$rrmdir) {
                foreach (array_diff (@scandir ($dir) ?: array (), array ('.', '..')) as $f) {
                        $path = $dir.DIRECTORY_SEPARATOR.$f;
                        is_dir ($path) ? $rrmdir ($path) : @unlink ($path);
                }
                @rmdir ($dir);
        };
        $rrmdir (FORUM_DATA);
});
//`formatText ()` needs this to decide whether a detected link is internal (no "external" rel) or not
define ('FORUM_URL', 'http://forum.example.test');

require_once FORUM_LIB.'utf8safe.php';
require_once FORUM_LIB.'functions.php';
