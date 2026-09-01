<?php
/* the access-gate page: shown by `requireAccess ()` in 'lib/functions.php' when 'access.txt' lists door passwords
   and the visitor hasn't entered one yet. `$error` is true if a wrong password was just submitted. deliberately
   self-contained (no theme, no external assets) so it works before anything else and can't be styled around. */
header ('HTTP/1.1 403 Forbidden');
header ('Content-Type: text/html; charset=utf-8');
$action = htmlspecialchars ((string) @$_SERVER['REQUEST_URI'], ENT_QUOTES);
$name   = htmlspecialchars (defined ('FORUM_NAME') ? FORUM_NAME : '', ENT_QUOTES);
?><!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo $name ?: 'Restricted'; ?></title>
<style>
 :root {color-scheme: dark light;}
 html {background: #1b1b1b; color: #b0b0b0;
       font: 16px/1.5 -apple-system, "Segoe UI", Roboto, "Liberation Sans", sans-serif;}
 body {min-height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center;}
 form {width: 280px; max-width: 84vw; padding: 30px 0; text-align: center;}
 h1   {margin: 0 0 22px; font-size: 18px; font-weight: normal; color: #ececec;}
 input, button {width: 100%; box-sizing: border-box; padding: 11px; font-size: 16px;
                border-radius: 4px; border: 1px solid #444;}
 input  {margin: 0 0 12px; background: #262626; color: #ddd;}
 button {border-color: #7fb0d8; background: #7fb0d8; color: #111; cursor: pointer;}
 .err   {margin: 0 0 14px; font-size: 14px; color: #d66;}
</style>
</head><body>
<form method="post" action="<?php echo $action; ?>">
	<h1><?php echo $name ?: 'This forum is private'; ?></h1>
	<?php if (!empty ($error)) echo '<p class="err">Wrong password.</p>'; ?>
	<input type="password" name="nnf_access_password" placeholder="Access password"
	       autocomplete="current-password" autofocus required>
	<button type="submit" name="nnf_access">Enter</button>
</form>
</body></html>
