<?php
$nnf_error_title  = 'Forum not ready';
$nnf_error_public = 'The web server this forum runs on needs to be updated before it can work. '
                  . 'If you’re the administrator, check the server software.';
$nnf_error_log    = 'Apache reports "'.@apache_get_version ().'"; NoNonsense Forum needs Apache 2.1 or newer';
require FORUM_LIB.'error.php';
