<?php
/* Tells PHPStan about the constants that 'start.php' (and 'config.default.php' / 'theme.php') `define ()` at
   request-time, before any of the analysed files run -- without this, every reference to them would be flagged as
   an undefined constant. Realistic types/values are used (not just `null`) so PHPStan can reason about e.g. the
   arithmetic done with `FORUM_POSTS` / `PAGE`. This file is never executed by the real application. */

foreach (array (
        //start.php: strings
        'FORUM_ROOT' => __DIR__, 'FORUM_LIB' => __DIR__.'/lib/', 'FORUM_PATH' => '/', 'PATH' => '', 'PATH_URL' => '',
        'PATH_DIR' => '/', 'SUBFORUM' => '', 'NAME' => '', 'PASS' => '', 'FORUM_URL' => 'http://example.test',
        'FORUM_LOCK' => '', 'THEME_ROOT' => __DIR__.'/themes/greyscale/', 'DATE_FORMAT' => 'd M Y',
        'THEME_TITLE' => '%1$s%2$s', 'THEME_TITLE_PAGENO' => ' + %1$u', 'THEME_TITLE_APPEND' => 'Append to %1$s',
        'THEME_TITLE_DELETE' => 'Delete %1$s?', 'THEME_TITLE_SEARCH' => 'Search: %1$s', 'THEME_REPLYNO' => '#%1$u.',
        'THEME_RE' => 'RE[%1$u]: %2$s', 'THEME_APPENDED' => ':: @%1$s added on %2$s', 'THEME_DEL_USER' => '',
        'THEME_DEL_MOD' => '', 'THEME_HTML_ERROR' => '',
        //start.php: booleans
        'HTACCESS' => true, 'FORM_SUBMIT' => false, 'AUTH' => false, 'AUTH_HTTP' => false, 'IS_ADMIN' => false,
        'IS_MOD' => false, 'IS_MEMBER' => false,
        //start.php: mixed
        'PAGE' => 1, 'LANG' => '',
        //config.default.php / config.php: strings
        'FORUM_USERS' => 'users', 'FORUM_NAME' => 'NoNonsense Forum', 'FORUM_TIMEZONE' => 'UTC',
        //config.default.php / config.php: booleans
        'FORUM_HTTPS' => false, 'FORUM_SEARCH' => true, 'FORUM_THEME' => 'greyscale', 'FORUM_ENABLED' => true,
        'FORUM_NEWBIES' => true,
        //config.default.php / config.php: integers
        'FORUM_THREADS' => 50, 'FORUM_POSTS' => 25, 'FORUM_SEARCH_RESULTS' => 25, 'SIZE_NAME' => 20,
        'SIZE_PASS' => 20, 'SIZE_TITLE' => 100, 'SIZE_TEXT' => 50000, 'SIZE_SEARCH' => 100,
        //theme.config.default.php (greyscale): strings
        'METRO_COLOUR' => '#222222', 'THEME_LOGO' => 'img/logo.png', 'THEME_LANGS' => '', 'THEME_LANG' => '',
        //index.php / thread.php / search.php: defined early in the file, referenced later in the same file
        'TITLE' => '', 'TEXT' => '', 'QUERY' => '', 'CAN_POST' => false, 'CAN_REPLY' => false, 'IS_STICKY' => false,
) as $constant => $value) if (!defined ($constant)) define ($constant, $value);
