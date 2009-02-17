<?php

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("polls_do_newpoll_end", "stv_hook_polls_do_newpoll_end");
$plugins->add_hook("polls_load", "stv_hook_polls_load");
$plugins->add_hook("polls_vote_process", "stv_hook_polls_vote_process");
$plugins->add_hook("polls_showresults_start", "stv_hook_polls_showresults_start");

function stv_hook_polls_do_newpoll_end()
{
	global $postoptions;
	if ($postoptions['stv'])
	{
		global $db;
		global $pid;
		$db->insert_query("stv_polls", array("pid" => $pid));
	}
}

function stv_hook_polls_load(&$poll)
{
	global $db;
	$r = $db->query("SELECT COUNT(*) as count FROM ".TABLE_PREFIX."stv_polls WHERE pid = ".intval($poll['pid']));
	$poll['stv'] = $db->fetch_field($r, 'count');
	$poll['multiple'] = 1;
}

function passOnVote($vote, &$winners, &$hopefuls) {
        $opts = explode(',',$vote);
        do {
                array_shift($opts);
                if (count($opts) == 0) {
                        // no further options in vote. Untransferrable
                      //  $WASTED_VOTES++;
                        return false;
                }
                if (!array_key_exists($opts[0], $hopefuls)) continue;
                if (!in_array($opts[0], $winners)) break;
        } while (true);
        $hopefuls[$opts[0]][] = implode(',', $opts);
        return true;
}


function stv_count($VOTES, $NUMCANDIDATES, $SEATS)
{
	$quota = intval(($NUMCANDIDATES / ($SEATS + 1)) + 1);

	print "Quota is $quota<br>";
	$winners = array();
	$hopefuls = array();
	for ($i=1; $i<=$NUMCANDIDATES; $i++) $hopefuls[(string)$i] = array();

	// assign first choices
	foreach ($VOTES as $v) {
		$opts = explode(',', $v);
		$hopefuls[$opts[0]][] = $v;
	}
	while (count($winners) < $SEATS) {
		print "<br><br>";
		foreach ($hopefuls as $key => $val) {
			print "$key: ".count($val)." votes<br>";
		}
		// find winners
		do {
			$did_find_winner = false;
			foreach ($hopefuls as $candidate => $votes) {
				if (count($votes) >= $quota) {
					$winners[] = $candidate;
					$numVotesToTransfer = count($votes) - $quota;
					# pass vote to next choice
					for ($i=0; ($numVotesToTransfer>0) && ($i<count($votes)); $i++) {
						if (passOnVote($votes[$i],
									&$winners,
									&$hopefuls)) {
							$numVotesToTransfer--;
						}
					}
					unset ($hopefuls[$candidate]);
				}
			}
		} while ($did_find_winner);

		print "Winners: ".implode(', ', $winners)."<br>";
		// eliminate last position
		$worst = null;
		$lowest = 1000;
		foreach ($hopefuls as $key => $votes) {
			if (count($votes) < $lowest) {
				$lowest = count($votes);
				$worst = $key;
			}
		}
		print "Culling $worst<br>";
		foreach ($hopefuls[$worst] as $vote) {
			passOnVote($vote, &$winners, &$hopefuls);
		}
		unset ($hopefuls[$worst]);

		if (count($hopefuls) + count($winners) == $SEATS) {
			foreach ($hopefuls as $key => $val) {
				$winners[] = $key;
			}
			break;
		}
	}

	print "Winners: " . implode(',', $winners).'<br>';
	return $winners;
}

function stv_hook_polls_showresults_start()
{
	global $poll;
	global $db;
	global $poll;
	// Do stv count of votes and shove into mybb_polls table
	if ($poll['stv']) {
		$query = $db->simple_select("stv_votes", "*", "pid='".intval($poll['pid'])."'");
		$votes = array();
		while ($a = $db->fetch_array($query)) {
			$votes[] = $a['votes'];
		}
		$winners = stv_count($votes, $poll['numoptions'], 2);
	}
}

function stv_hook_polls_vote_process()
{
	global $poll;
	global $mybb;
	global $option;
	global $db;
	if($poll['stv']) {
		$_votes = array();
		foreach($option as $voteoption => $vote) {
			$_votes[$voteoption] = intval($vote);
		}
		$vote = array();
		for ($i=1; $i<=count($option); $i++) {
			$voteoption = array_search($i, $option);
			// if the sequence is broken subsequent ranked votes
			// are ignored, which is how the scottish council
			// elections worked.
			if ($voteoption == false) break;
			$vote[] = $voteoption;
		}
		$vote = implode(',', $vote);
		// Build vote turd
		$db->insert_query("stv_voted", array(
					'pid' => $poll['pid'],
					'uid' => $mybb->user['uid']));
		$db->insert_query("stv_votes", array(
					'pid' => $poll['pid'],
					'votes' => $vote,
					'dateline' => time()));
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

function _template_showthread_poll_option_stv()
{
	return '<tr>
	<td class="trow1" width="1%"><input type="text" class="text" style="width:2.0em;" name="option[{$number}]" id="option[{$number}]" value="" /></td>
	<td class="trow2" colspan="3">{$option}</td>
	</tr>';
}
function stv_vote_activate()
{
	global $db;
	include MYBB_ROOT . '/inc/adminfunctions_templates.php';

	//add template modification
	$find     = '#' . preg_quote(get_find_string()) . '#';
	find_replace_templatesets('polls_newpoll', $find, get_replace_string(), 1);//FIXME Should raise error if this doesnt work

	$newtemp = array(
		"title" => 'showthread_poll_option_stv',
		"template" => '<p>Hello',
		"sid" => -2,
		"version" => 120,
		"dateline" => time()
	);
	$db->insert_query("templates", $newtemp);

	//FIXME these need keys, and need to think about nulls and defaults
	//TODO needs tested on non-MySQL databases
	$db->query("CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "stv_polls\n(pid int(10) PRIMARY KEY)");
	$db->query("CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "stv_voted\n(pid int(10),\nuid int(10))");
	$db->query("CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX .
			"stv_votes\n(stv_vid int(10) PRIMARY KEY AUTO_INCREMENT,\npid int(10),\nvotes varchar(64),\ndateline bigint(30))"); // votes is string ordered first preference voteoption first. eg: "5,3,7,8"
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
