<?php
/* Common layout for showing files */
function files_textarea_head($file) {
	$r = '<div class="options">
  <a href="javascript:toggle(\''.$file.'\');">Show/Hide</a> |
  <a href="javascript:selectAll(\''.$file.'\');">Select All</a>
</div>

<h2>' . ucwords($file) . '</h2>
<textarea rows="30" cols="80" class="textarea" id="'.$file.'">';
	return $r;
}

/* Get rid of comillas around text */
function find_text($text, $delimit_start = '`', $delimit_end = '`') {
	$start = strpos($text, $delimit_start);
	if ($start === false) return false;

	$end = strpos(substr($text, $start + 1), $delimit_end);
	if ($end === false) return false;

	return substr($text, $start + 1, $end);
}

/* Given a table SQL definition, search it's primary key */
function get_primary_key($text) {
	$text = explode("\n", $text);
	foreach ($text as $line) {
		if (preg_match('/PRIMARY KEY/', $line)) {
			/* Remove parenthesis */
			$key = explode('(', $line);
			$key = substr($key[1], 0, -1);
			/* Remove surrounding comillas? */
			if ($key[0] == '`') $key = substr($key,1,-2);
		}
	}
	return $key;
}
?>