<?php

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("polls_do_newpoll_end", "stv_hook_polls_do_newpoll_end");
$plugins->add_hook("polls_load", "stv_hook_polls_load");
$plugins->add_hook("polls_vote_process", "stv_hook_polls_vote_process");
$plugins->add_hook("polls_showresults_start", "stv_hook_polls_showresults_start");
$plugins->add_hook("polls_showresults_end", "stv_hook_polls_showresults_end");
$plugins->add_hook("showthread_poll_results", "stv_showthread_poll_results");

function stv_showthread_poll_results()
{
	global $pollclosed;
	global $templates;
	global $lang;
	global $poll;
	global $parser;
	global $optionsarray;
	global $polloptions;
	global $totpercent;
	if ($poll['stv']) {
		global $pollbox;
		$votesarray = explode("||~|~||", $poll['votes']);
		$polloptions = '';
		if (!$pollclosed) $polloptions = '<tr><td>You can\'t view the results yet because this is a single
transferable vote poll and the poll is still open.</td></tr>';
		for($i = 1; $i <= $poll['numoptions']; ++$i)
		{
			// Set up the parser options.
			$parser_options = array(
				"allow_html" => $forum['allowhtml'],
				"allow_mycode" => $forum['allowmycode'],
				"allow_smilies" => $forum['allowsmilies'],
				"allow_imgcode" => $forum['allowimgcode'],
				"filter_badwords" => 1
			);

			$option = $parser->parse_message($optionsarray[$i-1], $parser_options);
			if ($pollclosed) {
				$votes = $votesarray[$i-1] ? 'winner' : '';
				$optionbg = "trow2";
			} else {
				$votes = '---';
				$optionbg = "trow1";
			}

			$totalvotes = '';
			$number = $i;
			$votestar = "";

			$polloptions .= '<tr><td align="right" class="'.$optionbg.'">' . $option .
'</td><td class="'.$optionbg.'" colspan="3">' . $votes . '</td></tr>';
		//	eval("\$polloptions .= \"".$templates->get("showthread_poll_resultbit")."\";");
		}
		$lang->total_votes = sprintf("%d votes", $poll['numvotes']);
		eval("\$pollbox = \"".$templates->get("showthread_poll_results")."\";");
	}
}

function stv_hook_polls_do_newpoll_end()
{
	global $postoptions;
	global $mybb;
	if ($postoptions['stv'])
	{
		global $db;
		global $pid;
		$seats = intval($mybb->input['stvseats']);
		if (!$seats) $seats = 1;
		$db->insert_query("stv_polls", array("pid" => $pid, "seats" => $seats));
	}
}

function stv_hook_polls_load()
{
	global $db;
	global $poll;
	$r = $db->query("SELECT * FROM ".TABLE_PREFIX."stv_polls WHERE pid = ".intval($poll['pid']));
	$a = $db->fetch_array($r);
	if ($a) {
		$poll['stv'] = true;
		$poll['stvseats'] = $a['seats'];
		$poll['multiple'] = 1;
	}
}

function passOnVote($vote, &$winners, &$hopefuls) {
        $opts = explode(',',$vote);
        do {
                array_shift($opts);
                if (count($opts) == 0) {
                        // no further options in vote. Untransferable
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

	$winners = array();
	$hopefuls = array();
	for ($i=1; $i<=$NUMCANDIDATES; $i++) $hopefuls[(string)$i] = array();

	// assign first choices
	foreach ($VOTES as $v) {
		$opts = explode(',', $v);
		$hopefuls[$opts[0]][] = $v;
	}
	while (count($winners) < $SEATS) {
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
					$did_find_winner = true;
					break;
				}
			}
		} while ($did_find_winner and (count($winners) < $SEATS));

		if (count($winners) == $SEATS) break;

		// eliminate last position
		$worst = null;
		$lowest = 1000;
		foreach ($hopefuls as $key => $votes) {
			if (count($votes) < $lowest) {
				$lowest = count($votes);
				$worst = $key;
			}
		}
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

	return $winners;
}

function stv_hook_polls_showresults_end()
{
	global $poll;
	if ($poll['stv']) {
		global $pollclosed;
		global $templates;
		global $lang;
		global $parser;
		global $optionsarray;
		global $polloptions;
		global $totpercent;
		global $thread;
		$expiretime = $poll['dateline'] + $poll['timeout'];
		$now = TIME_NOW;
		if($poll['closed'] == 1 || $thread['closed'] == 1 || ($expiretime < $now && $poll['timeout'])) {
			$pollclosed = 1;
		}
		global $pollbox;
		$polloptions = '';
		if (!$pollclosed) $polloptions = '<tr><td>You can\'t view the results yet because this is a single
transferable vote poll and the poll is still open.</td></tr>';
		$votesarray = explode("||~|~||", $poll['votes']);
		for($i = 1; $i <= $poll['numoptions']; ++$i)
		{
			// Set up the parser options.
			$parser_options = array(
				"allow_html" => $forum['allowhtml'],
				"allow_mycode" => $forum['allowmycode'],
				"allow_smilies" => $forum['allowsmilies'],
				"allow_imgcode" => $forum['allowimgcode'],
				"filter_badwords" => 1
			);

			$option = $parser->parse_message($optionsarray[$i-1], $parser_options);
			if ($pollclosed) {
				$votes = $votesarray[$i-1] ? 'winner' : '';
				$optionbg = "trow2";
			} else {
				$votes = '---';
				$optionbg = "trow1";
			}
			$totalvotes = '';
			$number = $i;
			$votestar = "";

			//eval("\$polloptions .= \"".$templates->get("polls_showresults_resultbit")."\";");
			$polloptions .= '<tr><td align="right" class="'.$optionbg.'">' . $option .
'</td><td class="'.$optionbg.'" colspan="3">' . $votes . '</td></tr>';
		}
	}
}

function stv_hook_polls_showresults_start()
{
	global $poll;
	global $db;
	// Do stv count of votes and shove into mybb_polls table
	if ($poll['stv']) {
		$query = $db->simple_select("stv_votes", "*", "pid='".intval($poll['pid'])."'");
		$votes = array();
		while ($a = $db->fetch_array($query)) {
			$votes[] = $a['votes'];
		}
		$winners = stv_count($votes, $poll['numoptions'], $poll['stvseats']);
		$haswon = array();
		for ($i=1; $i<=$poll['numoptions']; $i++) {
			if (in_array($i, $winners)) {
				$haswon[] = 1;
			} else {
				$haswon[] = 0;
			}
		}
		$poll['votes'] = implode('||~|~||', $haswon);
		$db->query("UPDATE " . TABLE_PREFIX . "polls SET votes='" . $db->escape_string($poll['votes']) . "' WHERE pid=" . $poll['pid']);
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
		"description"		=> "A new poll feature that uses the single transferable vote system",
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
	find_replace_templatesets('polls_newpoll',
		'#' . preg_quote('<td class="trow1" valign="top"><strong>{$lang->options}</strong></td>') . '#',
		'<tr><td class="trow1" valign="top"><strong>Number of seats contested (for STV)</strong></td>
			<td class="trow1"><span class="smalltext">
				<label><input type="text" class="textbox"
name="stvseats" size="10" value="1"></label></span>
			</td></tr>
		<td class="trow1" valign="top"><strong>{$lang->options}</strong></td>', 1);

	// and my lovely new templates
	$newtemp = array(
		"title" => 'showthread_poll_option_stv',
		"template" => $db->escape_string(_template_showthread_poll_option_stv()),
		"sid" => -2,
		"version" => 120,
		"dateline" => time()
	);
	$db->insert_query("templates", $newtemp);

	//FIXME these need keys, and need to think about nulls and defaults
	//TODO needs tested on non-MySQL databases
	$db->query("CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "stv_polls\n(pid int(10) PRIMARY KEY, seats int(10) NOT NULL)");
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
