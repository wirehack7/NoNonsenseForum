<?php
$nnf_error_title  = 'This thread can’t be shown';
$nnf_error_public = 'The thread’s data is damaged and needs an administrator to repair it before it will load again.';
$nnf_error_log    = 'malformed XML in a thread feed'.(isset ($FILE) ? ' ("'.$FILE.'.rss")' : '');
require FORUM_LIB.'error.php';
