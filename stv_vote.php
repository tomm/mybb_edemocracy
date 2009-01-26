<?php

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("polls_do_newpoll_end", "addstv");

function addstv()
{
	global $postoptions;
	if ($postoptions['stv'])
	{
		global $db;
		global $pid;
		$db->insert_query("stv_polls", array("pid" => $pid));
	}
}

function stv_vote_info()
{
	return array(
		"name"			=> "STV vote",
		"description"		=> "A new poll feature that uses the single transferrable vote system",
		"website"		=> "http://www.iww.org",
		"author"		=> "IWW",
		"authorsite"		=> "http://www.iww.org",
		"version"		=> "0.0",
		"guid" 			=> "",
		"compatibility"		=> "14*"
	);
}

function get_find_string()
{
	return '<label><input type="checkbox" class="checkbox" name="postoptions[multiple]" value="1" {$postoptionschecked[\'multiple\']} />&nbsp;{$lang->option_multiple}</label>';
}

function get_replace_string()
{
	return '<label><input type="checkbox" class="checkbox" name="postoptions[stv]" value="1"  />&nbsp;<b>Use STV:</b> conduct this poll using Single Transferable Vote.</label><br />' . get_find_string();//FIXME need to support non-english languages
}
function stv_vote_activate()
{
	global $db;
	include MYBB_ROOT . '/inc/adminfunctions_templates.php';

	//add template modification
	$find     = '#' . preg_quote(get_find_string()) . '#';
	find_replace_templatesets('polls_newpoll', $find, get_replace_string(), 1);//FIXME Should raise error if this doesnt work

	//FIXME these need keys, and need to think about nulls and defaults
	//TODO needs tested on non-MySQL databases
	$db->query("CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "stv_polls\n(pid int(10))");
	$db->query("CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "stv_voted\n(pid int(10),\nvid int(10))");
	$db->query("CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "stv_votes\n(rid int(10),\npid int(10),\nvoteoption smallint(5),\nvotevalue smallint(5),\ndateline bigint(30))");//rid = random indentifier to anonymously group the votes of a given voter
}

function stv_vote_deactivate()
{
	global $db;
        include MYBB_ROOT . '/inc/adminfunctions_templates.php';

	//Remove template modification
	$find    = preg_quote(get_replace_string());
	$find    = '#' . $find . '#';
	$replace = get_find_string();

	find_replace_templatesets('polls_newpoll', $find, $replace, 0);
}
?>
