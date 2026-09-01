<?php
/* shared error page. a calling 'error_*.php' sets, before including this:

     $nnf_error_title    short heading for the visitor          (optional)
     $nnf_error_public   1-2 sentences, safe for ANYONE to see  (optional)
     $nnf_error_log      the real detail, for the server log    (optional)

   Nothing about the server -- paths, folder names, config option names, software
   versions -- is ever sent to the browser. The detail goes to the error log so
   the operator can still diagnose it (`docker compose logs forum`, or the Apache
   error log). */

if (!empty ($nnf_error_log)) @error_log ('NoNonsense Forum: '.$nnf_error_log);

if (!headers_sent ()) {
	header ('HTTP/1.1 500 Internal Server Error', true, 500);
	header ('Content-Type: text/html; charset=utf-8');
	header ('Retry-After: 30');
}

$nnf_t = isset ($nnf_error_title)  && $nnf_error_title  !== '' ? $nnf_error_title  : 'Something went wrong';
$nnf_p = isset ($nnf_error_public) && $nnf_error_public !== '' ? $nnf_error_public
       : 'The forum ran into a problem handling that request. Please try again in a moment.';
?><!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo htmlspecialchars ($nnf_t, ENT_QUOTES); ?></title>
<style>
 :root {color-scheme: dark light;}
 html {background: #1b1b1b; color: #b0b0b0;
       font: 16px/1.6 -apple-system, "Segoe UI", Roboto, "Liberation Sans", sans-serif;}
 body {margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;}
 main {max-width: 26em; padding: 2em;}
 h1   {margin: 0 0 .6em; font-size: 1.15em; font-weight: 600; color: #ececec;}
 p    {margin: .6em 0;}
</style>
</head><body>
<main>
	<h1><?php echo htmlspecialchars ($nnf_t, ENT_QUOTES); ?></h1>
	<p><?php echo htmlspecialchars ($nnf_p, ENT_QUOTES); ?></p>
</main>
</body></html>
<?php exit (1); ?>
