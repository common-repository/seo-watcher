<?php 


if(isset($_GET['seocAction'])) {
	$_REQUEST['seocKeyword'] = $_GET['seocAction'];

	if ($_GET['seocKeyword'] == 'data')
	{
		$keywords = get_option('seocKeyword');
		$_REQUEST['seocKeyword'] = $keywords[0];
	}

	require_once('../../../wp-config.php');


	require_once('ofc/php-ofc-library/open-flash-chart.php');
	require_once('ofc/php-ofc-library/ofc_line_dot.php');
	global $wpdb;
	$seocKeyworddata = $wpdb->prefix . 'seocKeyworddata';
	$resultlist = $wpdb->get_results("SELECT rank, time, page FROM " . $seocKeyworddata . " WHERE keyword ='" . mysql_real_escape_string(urldecode($_REQUEST['seocKeyword'])) . "' AND time > DATE_SUB(CURDATE(),INTERVAL 30 DAY) ORDER by time ASC");
	//die(print_r($resultlist));
	
	
	$title = new title( $_REQUEST['seocKeyword'] );
	
	$val = array();
	$labels = array();
	
	$i = 0;
	$last = '';
	foreach ($resultlist as $result)
	{
		$item = new dot_value($result->rank,'#5B56B6');
		$item->set_tooltip( $result->page . ' ' . __('on', 'seowatcher') . ' ' . $result->time . " " . __('at Position', 'seowatcher') . " #val#" );

		if ($last != $result->time)
			{
				foreach ($val as $key => $value)
				{
					$val[$key][] = null;
				}
				
			}
		if (isset($val[$result->page]))
		{
			$num = count($val[$result->page]);
			$num--;
			$val[$result->page][$num] = $item;
			if ($result->rank == 12)
			{	
				//die('test' . $num);
			}
			
		} else
		{
			
			for ($g = 1; $g < $i; $g++)
			{
				$val[$result->page][] = null;
			}	
			
			$val[$result->page][] = $item;	
		}
		if ($last != $result->time)
		{
			$labels[] = $result->time;
			$last = $result->time;
			$i++;
		}

	}
	
	
	$chart = new open_flash_chart();
	$chart->set_title( $title );
	$colour = array();
	$colour[] = '#5B56B6';
	$colour[] = '#D4C345';
	$colour[] = '#C95653';
	$colour[] = '#6363AC';
	$i = 0;
	foreach ($val as $value)
	{
		$area = new area();	
		$area->set_tooltip( "Rang #val#" );
		$area->set_colour( $colour[$i] );	
		$area->set_width( 3 );
		$area->set_fill_alpha( 0.0 );
		$area->set_values( $value );
		$chart->add_element( $area );
		$i++;
	}
	
	
	
	$x_labels = new x_axis_labels();
	$x_labels->set_steps( 1 );
	$x_labels->set_vertical();
	$x_labels->set_colour( '#A2ACBA' );
	$x_labels->set_labels( $labels );
	
	$x = new x_axis();
	$x->set_offset( false );
	$x->set_steps(4);
	$x->set_labels( $x_labels );
	$chart->set_x_axis( $x );
	
	$y = new y_axis();
	$y->set_range( 0, 100, 20 );
	$chart->add_y_axis( $y );
	
	
	echo $chart->toString();
	
	
}

if ($_REQUEST['seocKeyword'] == '')
{
	$keywords = get_option('seocKeyword');
	$_REQUEST['seocKeyword'] = $keywords[0];
}
if ($_GET['seocKeyword'] != '') {
	$_REQUEST['seocKeyword'] = urldecode($_REQUEST['seocKeyword']);

}
?>
<script type="text/javascript" src="<?=get_option('siteurl'); ?>/wp-content/plugins/seo-watcher/ofc/js/swfobject.js"></script>
<script type="text/javascript">
swfobject.embedSWF(
  "<?=get_option('siteurl'); ?>/wp-content/plugins/seo-watcher/ofc/open-flash-chart.swf", "seoc_chart", "690", "400",
  "9.0.0", "<?=get_option('siteurl'); ?>/wp-content/plugins/seo-watcher/ofc/expressInstall.swf",
  {"data-file":"<?=get_option('siteurl'); ?>/wp-content/plugins/seo-watcher/statistik.php?seocAction=<?php if($_REQUEST['seocKeyword'] != '') { echo urlencode($_REQUEST['seocKeyword']); } else { echo 'data'; } ;?>","loading":"<?php _e('Loading Statistic', 'seowatcher'); ?>..."}
  );
</script>


<div id="poststuff" class="metabox-holder">
<div class="wrap">
<h2>Seo Watcher Statistik</h2>

<div style="max-width: 700px;" class="postbox" id="keyworddiv"><h3 class="hndle"><span><?php _e('Select Keyword', 'seowatcher'); ?>:</span></h3>
	<div class="inside">
	<form action="<?=$_SEVER['PHP_SELF'];?>?page=<?=$_GET['page'];?>" method="POST">
	<p><label for="trackback_url"><?php _e('Statistic to show', 'seowatcher'); ?>:</label>   
	<select name="seocKeyword">
<?php 
$keywords = get_option('seocKeyword');
foreach ($keywords as $keyword) {
	$selected = '';
	if($_REQUEST['seocKeyword'] == $keyword)
		$selected = 'selected';
	echo "<option value='" . $keyword . "' " . $selected . ">" . $keyword . "</option>";
}
    ?>	
      
    </select><br/>
	<?php _e('Select desired Keyword.', 'seowatcher'); ?></p>	
	<input class="button-primary" type="submit" value="<?php _e('Show', 'seowatcher'); ?>"/></div>
	</form>
	</div>
	

<div style="max-width: 700px;" class="postbox" id="keyworddiv"><h3 class="hndle"><span><?php _e('Statistic for', 'seowatcher'); ?> <?=$_REQUEST['seocKeyword'];?></span></h3>
	<div class="inside">
<div id="seoc_chart"></div>
	</div>

</div>
</div>