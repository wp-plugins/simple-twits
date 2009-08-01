<?php
/*
Plugin Name: Simple Twits
Plugin URI: http://codequietly.com/projects/simple-twits
Description: Simple Twits allows you to display your Twitter posts and followers on your blog. You can randomize followers and choose how many followers and posts display. Its an excellent way to showcase your Twitter page on your blog.
Version: 0.3
Author: Dane Harrigan
Author URI: http://codequietly.com
*/



/***** display functions - start *****/

function twitter_follow_me_url($follow_message='Follow me on Twitter')
{
	$username = get_option('twitter_username');
	echo '<a href="http://twitter.com/'.$username.'">'.$follow_message.'</a>';
}

function twitter_messages($return_raw_data=false)
{
	$messages = array();

	$username = get_option('twitter_username');
	$twitter_messages_link = get_option('twitter_messages_link');
	$twitter_message_count = get_option('twitter_message_count');

	if(!empty($username) && !empty($twitter_message_count))
	{
		$url = "http://twitter.com/statuses/user_timeline/$username.xml";
		$ch = curl_init();	
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_GET, true);

		$xml = simplexml_load_string(curl_exec($ch));
		//print_r($xml);

		// get data
		$loop_count = 0;
		foreach($xml->status as $message)
		{
			$loop_count++;
			$text = str_replace('(expand)','',$message->text);
			$url = "http://twitter.com/$username/statuses/{$message->id}";

			if($twitter_messages_link)
				$text = "<a href=\"$url\">$text</a>";
			else
			{
				$split = explode(' ',$text);
				foreach($split as $key => $el)
				{
					if(preg_match('/^http:\/\//',$el))
						$split[$key] = '<a href="'.$el.'">'.$el.'</a>';

					if(preg_match('/^@/',$el))
						$split[$key] = preg_replace('/@+([A-Za-z0-9_]+)/','@<a href="http://twitter.com/$1">$1</a>',$el);
				}

				$text = implode(' ',$split);
			}

			$messages[] = array(
				'text'=>$text,
				'url'=>$url,
				'timestamp'=>get_relative_time($message->created_at)
			);

			if($twitter_message_count == $loop_count)
				break;
		}

		// present pretty data
		if(!$return_raw_data)
		{
			$results = array('<ul id="twitter_messages">');
			$message_count = count($messages);
			foreach($messages as $message)
				$results[] = "<li>{$message['text']}<p class=\"timestamp\">{$message['timestamp']}</p></li>";
			$results[] = '</ul>';

			echo implode("\n",$results);
		}
		else
			return $messages;
	}
}

function twitter_followers($return_raw_data=false)
{
	global $twitter_followers_xml;
	global $twitter_total_followers;

	$username = get_option('twitter_username');
	$password = get_option('twitter_password');
	$twitter_followers_count = get_option('twitter_followers_count');
	$twitter_followers_random = get_option('twitter_followers_random');
	$twitter_exclude_blocked_accounts = get_option('twitter_exclude_blocked_accounts');

	if(isset($twitter_followers_xml))
		$xml = $twitter_followers_xml;
	else
		$xml = get_twitter_xml($username, $password);

	if(!empty($xml) && !empty($twitter_followers_count))
	{
		$followers_to_display = $twitter_followers_count;
		$my_followers = array();
		$followers_count = $twitter_total_followers;

		if($followers_count>0)
		{
			if($twitter_exclude_blocked_accounts)
				$twitter_blocked_accounts = get_twitter_blocked($username, $password);

			$followers = $xml->user;
			$exclude = array();
			if($followers_count<$followers_to_display)
				$followers_to_display = $followers_count;

			if($twitter_followers_random)
			{
				while(true)
				{
					$index = rand(0,($followers_count-1));
					if(isset($followers[$index]) && !in_array($index, $exclude))
					{
						$follower_id = trim($followers[$index]->id[0]);
						if(!(!empty($twitter_blocked_accounts) && in_array($follower_id,$twitter_blocked_accounts)) )
						{
							$my_followers[] = $followers[$index];
							$exclude[] = $index;
						}
					}

					if(count($my_followers) == $followers_to_display)
						break;
				}
			}
			else
			{
				foreach($followers as $follower)
				{
					$follower_id = trim($follower->id[0]);
					if(!(!empty($twitter_blocked_accounts) && in_array($follower_id, $twitter_blocked_accounts)) )
					{
						$my_followers[] = $follower;
						if(count($my_followers) == $followers_to_display)
							break;
					}
				}
			}

			if(!$return_raw_data)
			{
				$results = array('<ul id="twitter_followers">');
				foreach($my_followers as $follower)
				{
					$username = $follower->screen_name;
					$url = "http://twitter.com/$username";
					$img = $follower->profile_image_url;
					$img = preg_replace('/_normal\.(.*)$/','_normal.$1',$img);

					$results[] = "<li><a href=\"$url\"><img src=\"$img\" alt=\"$username\" /></a></li>";
				}
				$results[] = '</ul>';
				echo implode("\n",$results);
			}
			else
			{
				foreach($my_followers as $key => $follower)
					$my_followers[$key] = array(
						'username'=>$follower->screen_name,
						'url'=>"http://twitter.com/{$follower->screen_name}",
						'avatar'=>preg_replace('/_normal\.(.*)$/','_normal.$1',$follower->profile_image_url)
					);

				return $my_followers;
			}
		}
	}
}

function twitter_total_followers($return_raw_data=false)
{
	global $twitter_total_followers;

	$username = get_option('twitter_username');

	if(empty($twitter_total_followers))
	{
		$password = get_option('twitter_password');
		get_twitter_xml($username, $password);
	}

	if(!$return_raw_data)
		echo "($twitter_total_followers) <a href=\"http://twitter.com/$username/followers\" class=\"twitter_followers\">More</a>";
	else
		return $twitter_total_followers;
}

/***** display functions - end *****/


function plural($num)
{
	if ($num != 1)
		return "s";
}

function get_relative_time($date)
{
	$diff = time() - strtotime($date);
	if ($diff<60)
		return "about " . $diff . " second" . plural($diff) . " ago";
	$diff = round($diff/60);
	if ($diff<60)
		return "about " . $diff . " minute" . plural($diff) . " ago";
	$diff = round($diff/60);
	if ($diff<24)
		return "about " . $diff . " hour" . plural($diff) . " ago";
	$diff = round($diff/24);
	return date("g:i A F jS", strtotime($date));
}

function get_twitter_xml($username, $password)
{
	global $twitter_followers_xml;
	global $twitter_total_followers;

	$xml = array();
	if(!empty($username) && !empty($password))
	{
		$url = "http://twitter.com/statuses/followers/$username.xml";

		$ch = curl_init();	
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
		curl_setopt($ch, CURLOPT_GET, true);

		$xml = simplexml_load_string(curl_exec($ch));
		$twitter_followers_xml = $xml;
		$twitter_total_followers = count($xml->user);
	}

	return $xml;
}

function get_twitter_blocked($username, $password)
{
	$blocked_ids = array();
	if(!empty($username) && !empty($password))
	{
		$url = "http://twitter.com//blocks/blocking/ids.xml";

		$ch = curl_init();	
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
		curl_setopt($ch, CURLOPT_GET, true);

		$xml = simplexml_load_string(curl_exec($ch));
		foreach($xml->id as $id)
			$blocked_ids[] = trim($id[0]);
	}

	return $blocked_ids;
}


function simple_twits_init()
{
	add_action('admin_menu','twitter_tools_config_page');
}

add_action('init','simple_twits_init');

function twitter_tools_config_page()
{
	if ( function_exists('add_submenu_page') )
		add_submenu_page('plugins.php', __('Simple Twits Configuration'), __('Simple Twits Configuration'), 'manage_options', 'simple-twits', 'twitter_tools_conf');
}


function twitter_tools_conf()
{
	twitter_tools_css();
	twitter_tools_display();
}

function twitter_tools_password()
{
	$password = get_option('twitter_password');
	if(!empty($password))
	{
		$new = '';
		for($i=0;$i<strlen($password);$i++)
			$new.='*';
		$password = $new;
	}

	return $password;
}

function twitter_set_selected($name, $value)
{
	$results = '';
	if(get_option($name) == $value)
		$results = ' selected="selected"';
	return $results;
}

function twitter_set_checked($name)
{
	$results = '';
	$value = get_option($name);
	if(!empty($value))
		$results = ' checked="checked"';
	return $results;
}

function twitter_tools_saved()
{
	if(!empty($_POST['twitter_tools_save']))
	{
		$twitter_username = get_option('twitter_username');
		$twitter_password = twitter_tools_password();

		$twitter_followers_count = get_option('twitter_followers_count');
		$twitter_followers_random = get_option('twitter_followers_random');
		$twitter_messages_link = get_option('twitter_messages_link');
		$twitter_message_count = get_option('twitter_message_count');

		$twitter_settings = array(
			'twitter_username',
			'twitter_password',
			'twitter_followers_count',
			'twitter_followers_random',
			'twitter_messages_link',
			'twitter_message_count',
			'twitter_exclude_blocked_accounts'
		);

		foreach($twitter_settings as $setting)
		{
			// save/update data
			if(empty($$setting) && !empty($_POST["$setting"]))
				add_option($setting, $_POST["$setting"]);
			else if(!empty($_POST["$setting"]))
			{
				if(!preg_match('/^\*+$/',$_POST["$setting"]))
					update_option($setting, $_POST["$setting"]);
			}

			// delete data
			if(!empty($$setting) && empty($_POST["$setting"]))
				delete_option($setting);
		}

		echo '<div class="twitter_message">Your settings have been modified successfully.</div>';
	}
}

function twitter_tools_display()
{?>
<div class="wrap">
	<h2><?php _e('Simple Twits Configuration'); ?></h2>

	<form method="post" id="twitter_tools">
		<div class="inside">
			<div id="postcustomstuff">
				<? twitter_tools_saved() ?>
				<p><strong>Add Twitter Information:</strong></p>
				<table id="newmeta">
					<thead>
						<tr>
							<th class="left twitter_header"><label for="twitter_username">Username</label></th>
							<th class="twitter_header"><label for="twitter_password">Password</label></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td class="left" id="newmetaleft">
								<input type="text" value="<?= get_option('twitter_username') ?>" tabindex="7" name="twitter_username" id="twitter_username"/>
							</td>
							<td>
								<input type="password" tabindex="8" name="twitter_password" id="twitter_password" value="<?= twitter_tools_password() ?>"/>
								<p class="twitter_note">The password is only required to display followers</p>
							</td>
						</tr>
						<tr>
							<td colspan="2">
								<p class="twitter_header">Message Settings</p>
								<div class="twitter_options">
									<select name="twitter_message_count" class="twitter_options" id="twitter_message_count">
										<option value="1"<?=twitter_set_selected('twitter_message_count', 1)?>>1</option>
										<option value="5"<?=twitter_set_selected('twitter_message_count', 5)?>>5</option>
										<option value="10"<?=twitter_set_selected('twitter_message_count', 10)?>>10</option>
										<option value="15"<?=twitter_set_selected('twitter_message_count', 15)?>>15</option>
										<option value="20"<?=twitter_set_selected('twitter_message_count', 20)?>>20</option>
									</select>
									<label for="twitter_message_count">Message(s) will display</label>
								</div>
								<div class="twitter_options">
									<input type="checkbox" name="twitter_messages_link" value="true" id="twitter_messages_link"<?=twitter_set_checked('twitter_messages_link')?>/>
									<label for="twitter_messages_link">Link messages to Twitter status page</label>
								</div>
							</td>
						</tr>
						<tr>
							<td colspan="2">
								<p class="twitter_header">Follower Settings</p>
								<div class="twitter_options">
									<select name="twitter_followers_count" id="twitter_followers_count">
										<option value="8"<?=twitter_set_selected('twitter_followers_count', 8)?>>8</option>
										<option value="10"<?=twitter_set_selected('twitter_followers_count', 10)?>>10</option>
										<option value="12"<?=twitter_set_selected('twitter_followers_count', 12)?>>12</option>
										<option value="14"<?=twitter_set_selected('twitter_followers_count', 14)?>>14</option>
										<option value="16"<?=twitter_set_selected('twitter_followers_count', 16)?>>16</option>
										<option value="18"<?=twitter_set_selected('twitter_followers_count', 18)?>>18</option>
										<option value="20"<?=twitter_set_selected('twitter_followers_count', 20)?>>20</option>
									</select>
									<label for="twitter_followers_count">Followers to display</label>
								</div>
								<div class="twitter_options">
									<input type="checkbox" name="twitter_followers_random" value="true" id="twitter_followers_random"<?=twitter_set_checked('twitter_followers_random')?>/>
									<label for="twitter_followers_random">Twitter followers will display at random</label>
								</div>

								<div class="twitter_options">
									<input type="checkbox" name="twitter_exclude_blocked_accounts" value="true" id="twitter_exclude_blocked_accounts"<?=twitter_set_checked('twitter_exclude_blocked_accounts')?>/>
									<label for="twitter_exclude_blocked_accounts">Exclude blocked users from followers</label>
								</div>

							</td>
						</tr>
						<tr>
							<td class="submit" colspan="2">
								<input type="submit" name="twitter_tools_save" value="Save Information" />
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</form>
</div>
<?}

function twitter_tools_css()
{?>
<style type="text/css">
<!--
	#twitter_tools {
		width: 500px;
	}
	#postcustomstuff #newmeta select#twitter_tools_count {
		width: 50px;
	}
	.checkbox_label {
		position: relative;
	}
	.twitter_message {
		border: 1px solid #FFCC00;
		background-color: #FFFFDD;
		padding: 10px;
	}
	#postcustomstuff table.no_border { border: none; }
	p.twitter_note {
		margin: 0;
		padding: 0 20px 0;
		text-align: right;
		font-size: 11px;
		font-style: italic;
	}
	p.twitter_header {
		background: #F1F1F1;
		font-weight: bold;
		padding: 5px 8px 8px;
		border-top: 1px solid #DFDFDF;
		text-align: center;
	}
	#postcustomstuff table td,
	#postcustomstuff table th.twitter_header {
		width: 50%;
	}
	#postcustomstuff #twitter_username,
	#postcustomstuff #twitter_password {
		width: 225px;
	}
	#postcustomstuff table div.twitter_options input,
	#postcustomstuff table div.twitter_options select {
		width: auto;
		margin: 0 8px 0 0;
	}
	div.twitter_options {
		line-height: 20px;
		padding: 5px 8px;
	}
	td.submit {
		text-align: right;
	}
-->
</style>
<?}?>
