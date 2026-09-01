<?php
$nnf_error_title  = 'Forum not ready';
$nnf_error_public = 'This forum isn’t finished being set up. If you’re the administrator, check the server configuration.';
$nnf_error_log    = '.htaccess is missing or not active AND the users folder is in its default web-exposed location. '
                  . 'enable AllowOverride / mod_rewrite, or move the users folder outside the web-root '
                  . '(FORUM_USERS -- see INSTALL.txt section 1.5)';
require FORUM_LIB.'error.php';
