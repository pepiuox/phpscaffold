<?php
class Scaffold {
	public $table = array();

	function __construct($project, $table_name, $table_info) {
		$columns = array();
		foreach($table_info['columns'] as $key => $value)
			if (is_array($value))
				$columns[] = array('type' => $value, 'name' => $key);
		$this->project = $project;
		$this->table   = $table_name;
		$this->id_key  = $table_info['id_key'];
		$this->columns = $columns;
	}

	function list_page() {
		$column_array = array();
		$return_string = "<?php
include('../inc.functions.php');\n";

		$return_string .= "\nprint_header('{$this->project['project_name']} » " . $this->_titleize($this->table) . "');

if (isset(\$_GET['msg'])) echo '<p id=\"msg\">'.\$_GET['msg'].'</p>';

/* Default search criteria (may be overriden by search form) */
\$conds = 'TRUE';
include('{$this->project['search_page']}');

/* Default paging criteria (may be overriden by paging functions) */
\$start     = 0;
\$per_page  = 50;
\$count_sql = 'SELECT COUNT({$this->id_key}) AS tot FROM `{$this->table}` WHERE ' . \$conds;
include('../inc.paging.php');

/* Get selected entries! */
\$sql = \"SELECT * FROM `{$this->table}` WHERE \$conds \" . get_order('{$this->table}') . \" LIMIT \$start,\$per_page\";

echo '<table>\n";
		$return_string .= "  <tr>\n";
		foreach($this->columns as $v) {
			$return_string .= '    <th>'. $this->_titleize($v['name']) . ' \' . put_order(\''.$v['name']."') . '</th>\n";
		}
		$return_string .= '    <th colspan="2" style="text-align:center">Actions</th>';
		$return_string .= "\n  </tr>\n';

\$r = mysqli_query(\$sql) or trigger_error(mysqli_error());
while(\$row = mysqli_fetch_array(\$r)) {\n";
		$return_string .= "	echo '  <tr>\n";

		foreach($this->columns as $v) {
			if($v['type']['blob'])
				$val = "limit_chars(nl2br(\$row['".$v['name']."']))";
			elseif($v['type']['date'] or $v['type']['datetime'])
				$val = "humanize(\$row['".$v['name']."'])";
			elseif($v['type']['bool'])
				$val = "(\$row['".$v['name']."'] ? 'Yes' : 'No')";
			else
				$val = "\$row['".$v['name']."']";

			$return_string .= "    <td>' . " . ($v['type']['bool'] ? "$val" : "htmlentities($val)") . " . '</td>\n";
		}
		$return_string .= "    <td><a href=\"{$this->project['crud_page']}?{$this->id_key}=' . \$row['{$this->id_key}'] . '\">Edit</a></td>
    <td><a href=\"{$this->project['crud_page']}?delete=1&amp;{$this->id_key}=' . \$row['{$this->id_key}'] . '\" onclick=\"return confirm(\'Are you sure?\')\">Delete</a></td>
  </tr>' . \"\n\";\n";
		$return_string .= "}\n\n";
		$return_string .= 'echo "</table>\n\n";

include(\'../inc.paging.php\');

echo \'<p><a href="' . $this->project['crud_page'] . '">New entry</a></p>\';

print_footer();
?>';

		return $return_string;
	}

	function crud_page() {
		$return_string = "<?php
include('../inc.functions.php');\n\n";

		$return_string .= "if (isset(\$_GET['delete'])) {
	mysqli_query(\"DELETE FROM `{$this->table}` WHERE `{$this->id_key}` = '\$_GET[{$this->id_key}]}'\");
	\$msg = (mysqli_affected_rows() ? 'Row deleted.' : 'Nothing deleted.');
	header('Location: {$this->project['list_page']}?msg='.\$msg);
}

\${$this->id_key} = (isset(\$_GET['{$this->id_key}']) ? \$_GET['{$this->id_key}'] : 0);
\$action = (\${$this->id_key} ? 'Editing' : 'Add new') . ' entry';\n\n";

		$column_array = array();

		$return_string .= "if (isset(\$_POST['submitted'])) {
	foreach(\$_POST AS \$key => \$value) { \$_POST[\$key] = mysqli_real_escape_string(\$value); }\n";
		$insert = "REPLACE INTO `{$this->table}` (";
		$counter = 0;
		foreach($this->columns as $v) {
			$insert .= "`$v[name]`" ;
			if ($counter < count($this->columns) - 1)
				$insert .= ", ";
			$counter++;
		}
		$insert .= ") VALUES ('\${$this->id_key}', ";

		$counter = 0;
		foreach ($this->columns as $v) {
			if ($v['name'] != $this->id_key) {
				$field = $v['name'];
				$val = $this->_parse($field, $v['type']);
				$insert .= "'$val'";
				if ($counter < count($this->columns) - 2)
					$insert .= ", ";
				$counter++;
			}
		}
		$insert .= ');';

		$return_string .= "	\$sql = \"$insert\";
	mysqli_query(\$sql) or die(mysqli_error());
	\$msg = (mysqli_affected_rows()) ? 'Edited row.' : 'Nothing changed.';
	header('Location: {$this->project['list_page']}?msg='.\$msg);
}


print_header(\"{$this->project['project_name']} » " . $this->_titleize($this->table) . " » \$action\");

\$row = mysqli_fetch_array ( mysqli_query(\"SELECT * FROM `{$this->table}` WHERE `{$this->id_key}` = '\${$this->id_key}' \"));
?>\n";

$return_string .= $this->_build_form($this->columns, 'Add / Edit') . '
<?php
print_footer();
?>';

		return $return_string;
	}

	function search_page() {
		$return_string = $this->_build_form($this->columns, 'Search', 'get', '_GET');
		$return_string .= "\n\n<?php\n";

		$return_string .= '$opts = array(';
		$cols = '';
		foreach($this->columns as $col) {
			$cols .= "'{$col['name']}_opts', ";
		}
		$return_string .= substr($cols, 0, -2) . ");\n"
. '/* Sorround "contains" search term between %% */
foreach ($opts as $o) {
	if (isset($_GET[$o]) && $_GET[$o] == \'like\') {
		$v = substr($o, 0, -5);
		$_GET[$v] = \'%\' . $_GET[$v] . \'%\';
	}
}'."\n\n";
		foreach($this->columns as $col) {
			$return_string .= "if (search_by('{$col['name']}'))
	\$conds .= \" AND {$col['name']} {\$_GET['{$col['name']}_opts']} '{\$_GET['{$col['name']}']}'\";\n";
		}

		return $return_string . "?>";
	}

	function _build_form($cols, $submit, $method = 'post', $value = 'row') {
		$is_search = ($submit == 'Search');

		$legend = $submit;
		if ($is_search)
			$legend = "<a href=\"#\" onclick=\"$('#search-form').slideToggle()\">$legend</a>";

		$res = '<form action="<?php echo $_SERVER[\'REQUEST_URI\'] ?>" method="'.$method.'">
<fieldset>
<legend>' . $legend . '</legend>
<div' . ($is_search ? ' id="search-form" style="display:none"' : '') . '>
<ul>
';
		foreach ($cols as $col)
			$res .= $this->_form_input($col, $value, $is_search);

		$res .= '</ul>
<p><input type="hidden" value="1" name="submitted" />
  <input type="submit" value="'.$submit.'" /></p>
</div>
</fieldset>
</form>';
		return $res;
	}

	function _form_input($col, $value, $is_search = false) {
		if ($col['name'] != $this->id_key) {

		$text = '  <li><label><span>' . $this->_titleize($col['name']) . ":</span>\n";
		if ($is_search)
			$text .= "    <?php echo search_options('".$col['name']."', (isset(\$_GET['".$col['name']."_opts']) ? stripslashes(\$_GET['".$col['name']."_opts']) : '')) ?></label>\n";
		$text .= '    ';

		/* Takes value either from $_GET['id'] or from $row['id'] */
		$val = '$'.$value.'[\''.$col['name'].'\']';
		$isset_val = '(isset('.$val.') ? stripslashes('.$val.') : \'\')';

		if ($col['type']['bool'])
			$text .= '<input type="checkbox" name="'.$col['name'].'" value="1" <?php echo (isset('.$val.') && '.$val.' ? \'checked="checked"\' : \'\') ?> />';
		elseif ($col['type']['date'])
			$text .= '<?php echo input_date(\''.$col['name'].'\', ' . $isset_val . ') ?>';
		elseif ($col['type']['datetime'])
			$text .= '<?php echo input_datetime(\''.$col['name'].'\', ' . $isset_val . ') ?>';
		elseif ($col['type']['blob'])
			$text .= '<textarea name="'.$col['name'].'" cols="40" rows="10"><?php echo ' . $isset_val . ' ?></textarea>';
		else
			$text .= '<input type="text" name="'.$col['name'].'" value="<?php echo (isset('.$val.') ? stripslashes('.$val.') : \'\') ?>" />';

		if (!$is_search) $text .= '</label>'; /* Could be closed after search_options */
		return $text . "</li>\n";
		} /* If not id column */
	}

	/* Merge split form data into single (SQL) data */
	function _parse($field, $type) {
		if ($type['date']) {
			$day  = $field . '_day';
			$mth  = $field . '_mth';
			$year = $field . '_year';
			$val = "\$_POST[$year]-\$_POST[$mth]-\$_POST[$day]";
		} elseif ($type['datetime']) {
			$seg  = $field . '_seg';
			$min  = $field . '_min';
			$hour = $field . '_hour';
			$day  = $field . '_day';
			$mth  = $field . '_mth';
			$year = $field . '_year';
			$val = "\$_POST[$year]-\$_POST[$mth]-\$_POST[$day] \$_POST[$hour]:\$_POST[$min]:\$_POST[$seg]";
		} else {
			$val = "\$_POST[$field]";
		}
		return $val;
	}

	function _titleize($name) {
		return ucwords(str_replace('_', ' ', trim($name)));
	}
}
?>
