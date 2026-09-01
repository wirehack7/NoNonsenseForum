<?php
$nnf_error_title  = 'That didn’t save';
$nnf_error_public = 'The forum can’t write to disk right now, so your post wasn’t saved. '
                  . 'If this keeps happening the site’s administrator needs to look into it.';
$nnf_error_log    = 'permission denied writing in "'.@getcwd ().'" (or the users folder). check ownership / '
                  . 'permissions of the data directory; for a bind-mounted Docker volume under SELinux, add ":z" '
                  . 'to the mount in docker-compose*.yml';
require FORUM_LIB.'error.php';
