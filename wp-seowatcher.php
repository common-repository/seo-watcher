<?php
/*
Plugin Name: Seo Watcher
Plugin URI: http://www.seo-watcher.net
Description: Beobachte deine Google Positionen und lass dir t&auml;glich die aktuellen Pl&auml;tze in deinem Dashboard anzeigen.
Author: www.seo-watcher.net
Author URI: http://www.seo-watcher.net
Version: 1.3.3
*/

/*  Copyright 2009  Cars-Media Ltd.  (email : info@seo-watcher.net)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


$plugin_dir = basename(dirname(__FILE__));
load_plugin_textdomain( 'seowatcher', 'wp-content/plugins/' . $plugin_dir, $plugin_dir );

if ('insert' == $_POST['action'])
{
	seowatcher_option_page_process();
}

if ('update' == $_POST['action'])
{
	seowatcher_update_keyworddata();
}


function seowatcher_setup() {
	/* Creating SeoC DB Tables if they don't exist */
	global $wpdb;
	$seocKeyworddata = $wpdb->prefix . 'seocKeyworddata';
	
	$time = time();
	$start_time = mktime(0, 0, 0, date('m', $time),date('d', $time),date('Y', $time));
	
	wp_schedule_event($start_time, 'daily', 'seoc_cronjob');
	
	if($wpdb->get_var("show tables like '$seocKeyworddata'") != $seocKeyworddata) {
		
		$sql = "CREATE TABLE " . $seocKeyworddata . " (
				`id` BIGINT( 100 ) NOT NULL AUTO_INCREMENT ,
				`keyword` VARCHAR( 255 ) NOT NULL ,
				`page` VARCHAR( 255 ) NOT NULL ,
				`rank` INT( 10 ) NOT NULL ,
				`time` DATE NOT NULL ,
				PRIMARY KEY ( `id` )
				);";
		$wpdb->query($sql);
	}
	add_option('seocKeyword', array('Nachrichten'));
	add_option('seocUrl', array('tagesschau.de'));
	add_option('seocRef', 'true');
	add_option('seocServer', __('google.com', 'seowatcher'));	
}

function seowatcher_uninstall() {
	global $wpdb;
	$seocKeyworddata = $wpdb->prefix . 'seocKeyworddata';
	
	wp_clear_scheduled_hook('seoc_cronjob');	
	//$wpdb->query("DROP TABLE " . $seocKeyworddata);
}

function seowatcher_update_keyworddata() {
	global $seocMessage;
	global $wpdb;
	
	$seocMessage = '<div class="updated fade below-h2" id="message" style="background-color: rgb(255, 251, 204);"><p>' . __('Refresh was started.', 'seowatcher') . '</p></div>';
	
	$seocKeyworddata = $wpdb->prefix . 'seocKeyworddata';
	
	$wpdb->query("DELETE FROM " . $seocKeyworddata . " WHERE time = CURDATE()");
	
	$keywords = get_option('seocKeyword');
	$url = get_option('seocUrl');
	$foundurls = array();
	$options['headers'] = array(
			'User-Agent' => 'Mozilla/5.0 (Windows; U; Windows NT 6.0; de; rv:1.9.0.7) Gecko/2009021910 Firefox/3.0.7'
			);
	foreach ($keywords as $keyword)
	{
		$startpage = 0;
		while (($startpage <= 90) && (count($foundurls[urlencode($keyword)]) < count($url)))
		{
			$requrl = 'http://www.' . get_option("seocServer") .  '/search?hl=de&q='. urlencode($keyword) . '&start=' . $startpage . '&sa=N';
			$response =  wp_remote_request($requrl, $options);
			
			$try = 1;
			while((wp_remote_retrieve_response_code($response) != 200) and ($try <= 3))
			{
				$response =  wp_remote_request($requrl, $options);
				$try++;	
			}

			// Get Results
			preg_match_all("|<li class=g>(.+)<!--n-->|U", $response['body'], $entrys);
			$localentry = 0;
			foreach ($entrys[1] as $entry)
			{
				//Filter Urls and exclude Google Special Pages
				$entry = preg_replace("|" . get_option("seocServer") . "/products|U", "shopping." . get_option("seocServer") .'/', $entry);

				preg_match("|http://(.+)\"|U", $entry, $entryurl);
				if (!preg_match("!(search." . get_option("seocServer") . "|news." . get_option("seocServer")  ."|shopping." . get_option("seocServer")  .")!", $entryurl[1]))
				{
					$localentry++;
					$entryurl = strtolower($entryurl[1]);
					//$entryurl = 'de.wikipedia.org/wiki/Computer';
					//Build Match pattern for Userdefined Pages
					$sitepattern = '!(';
					$spacer = '';
					$rules = false;
					foreach ($url as $togeturl)
					{	
						// Exclude found Urls
						if (!isset($foundurls[urlencode($keyword)][$togeturl]))
						{
							$rules = true;
							$sitepattern .= $spacer . strtolower($togeturl);
							$spacer = '|';
						} 				
					}
					if (!$rules)
					{
						break;
					}
					$sitepattern .= ')!';
					// Save Matches
					if(preg_match($sitepattern, $entryurl, $pattern)) {
						$keyrank = $startpage + $localentry;
						$foundurls[urlencode($keyword)][$pattern[1]] = $keyrank;
						$wpdb->query("INSERT INTO " . $seocKeyworddata . "( keyword, page, rank, time )	VALUES ( '" . $keyword . "' , '" . $pattern[1] . "' , '" . $keyrank . "' , CURDATE() )");
						
					}
					
				}
				
			}
			$startpage = $startpage + 10;
		}
	}
}

function array_trim($var) {
	if (is_array($var))
		return array_map("array_trim", $var);
	if (is_string($var))
		return trim($var);
	return $var;
}

function seowatcher_explode_comma_list($text) {
	$array = explode(',', $text, 4);
	unset($array[3]);
	return array_trim($array);
}

function seowatcher_supportlink() {
	if (get_option("seocRef") == 'true')
	{
		echo 'Positions by <a href="http://www.Seo-Watcher.net">Seo-Watcher</a>';
	}
	
}

function seowatcher_dashboard_content() {
	global $wpdb;
	$seocKeyworddata = $wpdb->prefix . 'seocKeyworddata';
	$resultlist = $wpdb->get_results("SELECT main.*, (SELECT rank FROM " . $seocKeyworddata . " WHERE time = DATE_SUB(CURDATE(),INTERVAL 1 DAY) and main.keyword = keyword and main.page = page  ORDER by time ASC LIMIT 1 ) as yesterday, (SELECT rank FROM " . $seocKeyworddata . " WHERE time = DATE_SUB(CURDATE(),INTERVAL 7 DAY) and main.keyword = keyword and main.page = page ORDER by time ASC LIMIT 1 ) as befweek FROM " . $seocKeyworddata . " as main WHERE time = CURDATE() ORDER BY keyword, page ASC");
	
	?>
	<div id="dashboard_right_now">
	<p class="sub"><?php _e('Current Google Ranking', 'seowatcher'); ?> ( <a href="<?=get_option('siteurl'); ?>/wp-admin/options-general.php?page=seo-watcher/statistik.php"><?php _e('Statistic', 'seowatcher'); ?></a> )</p>
	<div class="table">
	<table>
	<tbody>
	<tr class="first">
	<td class="first t pages" style="text-align:left;"><b><?php _e('Keyword', 'seowatcher'); ?></b></td>
	<td class="first t pages"> <b><?php _e('URL', 'seowatcher'); ?></b> </td>
	<td class="first t pages" style="text-align:right;width:90px;"> <b><?php _e('Last Week', 'seowatcher'); ?></b> </td>
	<td class="first t pages" style="text-align:right;"> <b><?php _e('Yesterday', 'seowatcher'); ?></b> </td>
	<td class="first t pages" style="text-align:right;"> <b><?php _e('Today', 'seowatcher'); ?></b> </td>
	</tr>
	<?
	foreach ($resultlist as $result)
	{
		if ($result->yesterday <= 0)
		{
			$result->yesterday = '-';
		}
		$startpage = (floor($result->rank/10))*10;
		
		echo '<tr><td class="first t pages" style="text-align:left;width:100px;"><a href="' . get_option('siteurl') . '/wp-admin/options-general.php?page=seo-watcher/statistik.php&seocKeyword=' . urlencode($result->keyword) . '">' . $result->keyword . '</a></td><td class="t pages"><a target="_blank" href="http://www.' . get_option("seocServer") .  '/search?q=' . $result->keyword . '&start=' . $startpage . '&sa=N">' .$result->page . ' </td><td class="b" style="">' .  $result->befweek . '</td><td class="b" style="">' .  $result->yesterday . '</td><td class="b" style="color:red;">'  .  $result->rank . '</td></tr>'; 
	}
	?>
	</tbody></table>
	</div>
	</div>
	<?php
}

function seowatcher_option_page_process() {
	global $seocMessage;
	$seocMessage = '<div class="updated fade below-h2" id="message" style="background-color: rgb(255, 251, 204);"><p>' . __('Options were updated.', 'seowatcher') .  '</p></div>';
	
	update_option('seocKeyword', seowatcher_explode_comma_list($_POST['seocKeyword']));
	update_option('seocUrl', seowatcher_explode_comma_list(strtolower($_POST['seocUrl'])));
	if ($_POST['seocServer'] == 'google.de') {
		update_option('seocServer', 'google.de');
	} elseif ($_POST['seocServer'] == 'google.com') {
		update_option('seocServer', 'google.com');
	} elseif ($_POST['seocServer'] == 'own')
	{
		if (preg_match("!^google\.(.+)!",$_POST['seocServerOwn']))
		{
			update_option('seocServer', $_POST['seocServerOwn']);
		} else {
			$seocMessage = '<div class="updated fade below-h2" id="message" style="background-color: rgb(255, 251, 204);"><p>' . __('Wrong pattern for other server.', 'seowatcher') .  '</p></div>';
		}
		
	}
	if ($_POST['seocRef'] == 'on')
	{
		update_option('seocRef', 'true');
	} else {
		update_option('seocRef', 'false');
	}
	
}

function seowatcher_option_page_content() {
	global $seocMessage;
	?>
	<div id="poststuff" class="metabox-holder">
	
	<div id="side-info-column" class="inner-sidebar" style="margin-right:50px;">
	<div style="position: relative;" id="side-sortables">
	<div id="submitdiv" class="postbox" style="margin-top:89px;"><h3 class="hndle"><span><?php _e('About Seo Watcher', 'seowatcher'); ?></span></h3>
	<div class="inside" style="padding:5px;">
	<?php _e('Seo Watcher helps you to keep track of your Google rankings.', 'seowatcher'); ?>
	<br> <?php _e('In your <b>Dashboard</b> you\'ll find your current daily statistics.', 'seowatcher'); ?>
	</div>
	</div>
	
	<div id="submitdiv" class="postbox"><h3 class="hndle"><span><?php _e('Support us!', 'seowatcher'); ?></span></h3>
	<div class="inside" style="padding:5px;">
	<?php _e('A lot of work has been put into the development of Seo Watcher.<br> If you want to make sure that Seo Watcher remains free, please consider donating.', 'seowatcher'); ?>
	<br>
	<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
	<input type="hidden" name="cmd" value="_s-xclick">
	<input type="hidden" name="hosted_button_id" value="4777471">
	<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="Jetzt einfach, schnell und sicher online bezahlen Ã¢â‚¬â€œ mit PayPal.">
	<img alt="" border="0" src="https://www.paypal.com/de_DE/i/scr/pixel.gif" width="1" height="1">
	</form>
	</div>
	</div>
	
	
	<div id="submitdiv" class="postbox"><h3 class="hndle"><span><?php _e('Why can I define so few keywords?', 'seowatcher'); ?></span></h3>
	<div class="inside" style="padding:5px;">
	<?php _e('This limitation is in place for your protection. If there are too many search requests being sent to Google by one IP, it might happen, that Google blocks the IP for a while. Hence better safe than sorry.', 'seowatcher'); ?>
	</div>
	</div>
	
	
	<div id="submitdiv" class="postbox"><h3 class="hndle"><span><?php _e('Why does the activation of the plugin take so long?', 'seowatcher'); ?></span></h3>
	<div class="inside" style="padding:5px;">
	<?php _e('At the day\'s first activation, the cronjob will be carried out immediately inside of the same request. This might take some time additional to the process of activation.', 'seowatcher'); ?>
	</div>
	</div>
	
	
	<div id="submitdiv" class="postbox"><h3 class="hndle"><span><?php _e('When will the data be refreshed?', 'seowatcher'); ?></span></h3>
	<div class="inside" style="padding:5px;">
	<?php _e('The cronjob runs every day at midnight. As soon as the first user after midnight requests a page of your blog, the cronjob is being run.', 'seowatcher'); ?>
	</div>
	</div>
	
	</div>	
	</div>
	
	<div id="post-body" class="has-sidebar">
	<div id="post-body-content" class="has-sidebar-content">
	<div class="wrap">
	
	<form name="form1" method="post" action="<?=$location ?>">
	<h2><?php _e('Seo Watcher Options', 'seowatcher'); ?> ( <a href="<?=get_option('siteurl'); ?>/wp-admin/options-general.php?page=seo-watcher/statistik.php"><?php _e('Statistic', 'seowatcher'); ?></a> )</h2>
	<?=$seocMessage; ?>
	<br>
	<div id="keyworddiv" class="postbox" style="max-width:700px;"><h3 class="hndle"><span><?php _e('Keywords', 'seowatcher'); ?></span></h3>
	<div class="inside">
	<p><label for="trackback_url"><?php _e('Keywords to check:', 'seowatcher'); ?></label> <input name="seocKeyword" style="width:400px;" size="150" id="seocKeyword" tabindex="1" value="<?=implode(', ', get_option("seocKeyword"));?>" type="text"><br>
	<?php _e('(Seperate multiple Keywords by comma.)</p><p>Each of these keywords will be read for each single URL, so you can compare your Google ranking with other websites.</p><p><b>You can define at most 3 keywords.</b></p>', 'seowatcher'); ?>
	</div>
	</div>
	<div id="keyworddiv" class="postbox" style="max-width:700px;""><h3 class="hndle"><span><?php _e('URLs', 'seowatcher'); ?></span></h3>
	<div class="inside">
	<p><label for="seocUrl"><?php _e('URLs to check:', 'seowatcher'); ?></label> <input name="seocUrl" style="width:300px;" size="150"  id="seocUrl" tabindex="1" value="<?=implode(', ', get_option("seocUrl"));?>" type="text"><br> 
	<?php _e('(Seperate multiple URLs by comma.)</p><p> The URLs have to be entered using the pattern subdomain.domain.com or domain.com.</p><p><b>You can define at most 3 URLs.</b></p>', 'seowatcher'); ?>
	</div>
	</div>
	


	<div id="keyworddiv" class="postbox" style="max-width:700px;""><h3 class="hndle"><span><?php _e('Google Server:', 'seowatcher'); ?></span></h3>
	<div class="inside">
	<p><label for="seocServer"><br />
		<input type="radio" name="seocServer" value="google.de" <?php if(get_option("seocServer") == 'google.de') { echo 'checked = "true"';} ?>> google.de<br>
    <input type="radio" name="seocServer" value="google.com" <?php if(get_option("seocServer") == 'google.com') { echo 'checked = "true"';} ?>> google.com<br>
    <input type="radio" name="seocServer" value="own" <?php if(get_option("seocServer") != 'google.com' AND get_option("seocServer") != 'google.de') { echo 'checked = "true"';} ?>> <?php _e('other server:', 'seowatcher'); ?><input name="seocServerOwn" style="width:100px;" size="150"  id="seocServerOwn" tabindex="1" value="<?=get_option("seocServer");?>" type="text"> <br>
    </label>
    <?php _e('Other server pattern: google.xxx (google.nl, google.ch...)', 'seowatcher'); ?><br>
	<?php _e('Select your favourite google server.', 'seowatcher'); ?></p>
	
	</div>
	</div>
	
	<div id="keyworddiv" class="postbox" style="max-width:700px;""><h3 class="hndle"><span><?php _e('Your Support:', 'seowatcher'); ?></span></h3>
	<div class="inside">
	<p><label for="seocUrl"> <input name="seocRef" style="width:10px;" id="seocRef" tabindex="1" <?php if(get_option("seocRef") != 'false') { echo 'checked = "true"'; };?> type="checkbox"> <?php _e('Activate Linking', 'seowatcher'); ?></label><br> 
	<?php _e('Help us by placing an undisruptive link in your footer.</p>', 'seowatcher'); ?>
	
	</div>
	</div>
	
	<input type="submit" class="button-primary" value="<?php _e('Save'); ?>" />
	<input name="action" value="insert" type="hidden" />
	</form>
	<br><br><br>
	
	<form name="form1" method="post" action="<?=$location ?>">
	<div id="keyworddiv" class="postbox" style="max-width:700px;""><h3 class="hndle"><span><?php _e('Immediate Refresh', 'seowatcher'); ?></span></h3>
	<div class="inside">
	<?php _e('<p>The keyword data will be refreshed automatically, every 24 hours. By changing the keywords or URLs, you are triggering a manual refresh.</p><p><b>Too frequent refreshes may lead to problems!</b></p>', 'seowatcher'); ?>
	<input type="submit" class="button-primary" value="<?php _e('Update'); ?>" />
	<input name="action" value="update" type="hidden" />
	</div>
	</div>
	</form>
	
	</div>
	</div>
	</div>
	<?php
} 

function seowatcher_dashboard_setup() {
	wp_add_dashboard_widget( 'seowatcher_dashboard_content', __( 'Seo Watcher' ), 'seowatcher_dashboard_content' );
}

function seowatcher_option_page_setup() {
	add_options_page('Seowatcher', 'Seo Watcher', 9, __FILE__, 'seowatcher_option_page_content'); 
}

/**
 * Hooks
 */
register_activation_hook( __FILE__, 'seowatcher_setup' );
register_deactivation_hook(__FILE__, 'seowatcher_uninstall');
add_action('seoc_cronjob', 'seowatcher_update_keyworddata');
add_action('admin_menu', 'seowatcher_option_page_setup');
add_action('wp_dashboard_setup', 'seowatcher_dashboard_setup');
add_action('wp_footer', 'seowatcher_supportlink');
