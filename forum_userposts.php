<?php
/*
 * e107 website system
 *
 * Copyright (C) 2008-2011 e107 Inc (e107.org)
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 *
 * User posts page
 *
 * $URL$
 * $Id$
 *
*/
require_once('class2.php');
 
e107::lan('forum', "front", true);
 
$e107 = e107::getInstance();
$sql = e107::getDb();
$pref = e107::getPref();
$tp = e107::getParser();
$ns = e107::getRender();

require_once(HEADERF);

$action = 'exit';
if (e_QUERY)
{
  $tmp = explode('.', e_QUERY);
  $from = intval($tmp[0]);			// Always defined
  $action = varset($tmp[1],'exit');
  if (!isset($tmp[2])) $action = 'exit';
  $id = intval(varset($tmp[2],0));
  if ($id <= 0) $action = 'exit';
  if (($id != USERID) && !check_class(varset($pref['memberlist_access'], 253))) $action = 'exit';
  unset($tmp);
}

if(isset($_POST['fsearch']))
{
	$action = 'forums';
}

if ($action == 'exit')
{
	e107::redirect();
	exit;
}

if ($action == 'forums')
{
	
    
    require_once (e_PLUGIN.'forum/forum_class.php');
	$forum = new e107forum();

	$forumList = implode(',', $forum->getForumPermList('view'));


	/*if(is_numeric($id))
	{
		$uinfo = e107::user($id);
		$fcaption = UP_LAN_0.' '.$uinfo['user_name'];
	}
	else
	{
		$user_name = 0;
	}*/
	if($id == e107::getUser()->getId())
	{
		$user_name = USERNAME;
	}
	else
	{
		$user_name = e107::getSystemUser($id, false)->getName(LAN_ANONYMOUS);
	}

	if(!$user_name)
	{
		e107::redirect();
		exit;
	}

	//	$fcaption = UP_LAN_0.' '.$user_name;
	$fcaption = str_replace('[x]', $user_name, LAN_FORUM_UP_00);
 
 
	$FORUM_USERPOSTS_TEMPLATE = e107::getTemplate('forum', 'forum_userposts');

	$s_info = '';
	$_POST['f_query'] = trim(varset($_POST['f_query']));
	if ($_POST['f_query'] !== '')
	{
		$f_query = $tp->toDB($_POST['f_query']);
		$s_info = "AND (t.thread_name REGEXP('".$f_query."') OR p.post_entry REGEXP('".$f_query."'))";
		$fcaption = str_replace('[x]', $user_name, LAN_FORUM_UP_12);
	}

	$qry = "
	SELECT SQL_CALC_FOUND_ROWS p.*, t.*, f.* FROM `#forum_post` AS p
	LEFT JOIN `#forum_thread` AS t ON t.thread_id = p.post_thread
	LEFT JOIN `#forum` AS f ON f.forum_id = p.post_forum
	WHERE p.post_user = {$id}
	AND p.post_forum IN ({$forumList})
	{$s_info}
	ORDER BY p.post_datestamp DESC LIMIT {$from}, 10
	";

	$debug = deftrue('e_DEBUG');

	$sqlp = e107::getDb('posts');

	if (!$sqlp->gen($qry))
	{
		$ftext .= "<span class='mediumtext'>". LAN_FORUM_UP_08.'</span>';
	}
	else
	{
		$gen = e107::getDate();
		$vars = new e_vars();

		$userposts_forum_table_string = '';
		while($row = $sqlp->fetch())
		{

			if(empty($row))
			{
				continue; 
			}

			$datestamp = $gen->convert_date($row['post_datestamp'], 'short');
			if ($row['thread_datestamp'] == $row['post_datestamp'])
			{
				$vars->USERPOSTS_FORUM_TOPIC_PRE = LAN_FORUM_1003.': ';
			}
			else
			{
				$vars->USERPOSTS_FORUM_TOPIC_PRE = LAN_FORUM_UP_15.': ';
			}


			$row['forum_sef'] = $forum->getForumSef($row);
			$row['thread_sef'] = $forum->getThreadSef($row);

			$forumUrl = e107::url('forum', 'forum', $row);

			$postNum = $forum->postGetPostNum($row['post_thread'], $row['post_id']);
			$postPage = ceil($postNum / $forum->prefs->get('postspage'));

			$postUrl = e107::url('forum', 'topic', $row, array('query' => array('p' => $postPage), 'fragment' => 'post-' . $row['post_id']));

			$vars->USERPOSTS_FORUM_ICON = "<img src='".e_PLUGIN."forum/images/".IMODE."/new_small.png' alt='' />";
			$vars->USERPOSTS_FORUM_TOPIC_HREF_PRE = "<a href='".$postUrl."'>"; //$e107->url->getUrl('forum', 'thread', "func=post&id={$row['post_id']}")
			$vars->USERPOSTS_FORUM_TOPIC = $tp->toHTML($row['thread_name'], true, 'USER_BODY', $id); 
			$vars->USERPOSTS_FORUM_NAME_HREF_PRE = "<a href='".$forumUrl."'>"; //$e107->url->getUrl('forum', 'forum', "func=view&id={$row['post_forum']}")
			$vars->USERPOSTS_FORUM_NAME = $tp->toHTML($row['forum_name'], true, 'USER_BODY', $id);
			$vars->USERPOSTS_FORUM_THREAD = $tp->toText($row['post_entry'], true, 'DESCRIPTION', $id);
			$vars->USERPOSTS_FORUM_DATESTAMP = LAN_FORUM_UP_11." ".$datestamp;

 
			$userposts_forum_table_string .= $tp->simpleParse($FORUM_USERPOSTS_TEMPLATE['forum_table'], $vars);
		}

		$vars->emptyVars();

		$ftotal = $sqlp->foundRows();

		$url = e107::url('forum', 'userposts', array("query"=> "[FROM].forums.". $id));
 
		$parms = $ftotal.",10,".$from.",".$url;

		$vars->NEXTPREV = $ftotal ? $tp->parseTemplate("{NEXTPREV={$parms}}") : '';
		if($vars->NEXTPREV) $vars->NEXTPREV =  str_replace('{USERPOSTS_NEXTPREV}', $vars->NEXTPREV, $FORUM_USERPOSTS_TEMPLATE['np_table']);
		$vars->USERPOSTS_FORUM_SEARCH_VALUE = htmlspecialchars($_POST['f_query'], ENT_QUOTES, CHARSET);
		$vars->USERPOSTS_FORUM_SEARCH_FIELD = "<input class='tbox input' type='text' name='f_query' size='20' value='{$vars->USERPOSTS_FORUM_SEARCH_VALUE}' maxlength='50' />";
		$vars->USERPOSTS_FORUM_SEARCH_BUTTON = "<input class='btn btn-default btn-secondary button' type='submit' name='fsearch' value='". LAN_SEARCH."' />";
		$vars->USERPOSTS_FORUM_SEARCH = "<input class='tbox' type='text' name='f_query' size='20' value='{$vars->USERPOSTS_FORUM_SEARCH_VALUE}' maxlength='50' /> <input class='btn btn-default btn-secondary button' type='submit' name='fsearch' value='". LAN_SEARCH."' />";

 
		$userposts_forum_table_start = $tp->simpleParse($FORUM_USERPOSTS_TEMPLATE['forum_table_start'], $vars);
 
		$userposts_forum_table_end = $tp->simpleParse($FORUM_USERPOSTS_TEMPLATE['forum_table_end'], $vars);

		$ftext = $userposts_forum_table_start.$userposts_forum_table_string.$userposts_forum_table_end;
	}

	$ns->tablerender($fcaption, $ftext);

}
else
{
	e107::redirect();
	exit;
}
 

require_once(FOOTERF);

 


