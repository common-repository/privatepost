<?php /**/ ?>


<?php
/*
Plugin Name: PrivatePost
Plugin URI: 
Description: This plugin adds the ' - Private' at the end of posts which are private
Author: Chris Black
Version: 1.7
Author URI: http://www.cjbonline.org

This plugin is a full featured private post management interface. It allows you to manage all private post's publishing status via the "Manage" admin menu. 

Allows you to have just a chunk of your post be private by enclosing it in: [private] This txt is private [/private]

It also provides the function "show_private_posts()" which can be used on the main page to list recent private posts. It uses the option 'posts_per_page' to determine how many recent private posts to display.

To Use: Upload to your plugins directory and then enable the plugin. Go to the options page and then select "PrivatePost" update the options to your specs.

*/

//add_filter('the_title', 'privatize_the_title');
add_action('admin_menu', 'add_private_manage_panel');
add_action('admin_menu', 'add_private_options_panel');
add_filter('the_content','CjB_PartialPrivate');
add_action('widgets_init', 'widget_privatepost_init');
add_filter('posts_where_request', 'CjB_postsWhere');
//add_filter('posts_results', 'CjB_postsResults');
add_filter('posts_request', 'CjB_postsRequest');
add_filter('the_posts', 'CjB_thePosts');

if (!is_admin()) {
  add_filter('query', 'CjB_Query');
}

function CjB_thePosts($posts) {
	global $wpdb, $userdata;
//	print_r($userdata);
//	print_r($_SESSION['postsRequest']);
	if ($userdata->ID != '') {
		$posts = $wpdb->get_results($_SESSION['postsRequest']);
	}
	
	return $posts;
}

function CjB_postsRequest($request) {
	$_SESSION['postsRequest'] = $request;
	return $request;
}

/*function CjB_postsResults($request) {
	print("<pre>");
	print_r($request);
	print("</pre>");

	global $userdata;
	if ($userdata->ID != '') {
		$request->post_status = 'publish';
	}

	return $request;
}*/

function CjB_postsWhere($where) {
	global $wpdb, $userdata;
	if ($userdata->ID != '') {
		$search = "AND " . $wpdb->posts . ".post_type = 'post'";
//		print("Search:" . $search);
		$where = str_replace($search,"AND (" . $wpdb->posts . ".post_type = 'post' OR " . $wpdb->posts . ".post_type = 'attachement')", $where);
	}
//	print_r($where);
	return $where;
}

function widget_privatepost_init() {
	
	// Check for the required API functions
	if ( !function_exists('register_sidebar_widget') || !function_exists('register_widget_control') )
		return;

	// This saves options and prints the widget's config form.
	function widget_privatepost_control() {
		$options = $newoptions = get_option('widget_privatepost');
		if ( $_POST['privatepost-submit'] ) {
			$newoptions['title'] = strip_tags(stripslashes($_POST['privatepost-title']));
		}
		if ( $options != $newoptions ) {
			$options = $newoptions;
			update_option('widget_privatepost', $options);
		}
	?>
				<div style="text-align:right">
				<label for="privatepost-title" style="line-height:35px;display:block;"><?php _e('Widget title:', 'widgets'); ?> <input type="text" id="privatepost-title" name="privatepost-title" value="<?php echo wp_specialchars($options['title'], true); ?>" /></label>
				<input type="hidden" name="privatepost-submit" id="privatepost-submit" value="1" />
				</div>
	<?php
	}

	// This prints the widget
	function widget_privatepost($args) {
		extract($args);
		$result = check_for_privates();
		if ($result) {
	
			$defaults = array('count' => 10, 'username' => 'wordpress');
			$options = (array) get_option('widget_privatepost');
	
			foreach ( $defaults as $key => $value )
				if ( !isset($options[$key]) )
					$options[$key] = $defaults[$key];

			echo $before_widget;
			echo $before_title . "{$options['title']}" . $after_title;
			show_private_posts();
			echo $after_widget;
		}
	}

	// Tell Dynamic Sidebar about our new widget and its control
	register_sidebar_widget(array('privatepost', 'widgets'), 'widget_privatepost');
	register_widget_control(array('privatepost', 'widgets'), 'widget_privatepost_control');
	
}


function CjB_PartialPrivate ($content) {
	get_currentuserinfo();
	global $user_ID;
	$PrivateColor = get_option('PrivateColor');
	$DelimStart = "[private]";
	$DelimEnd = "[/private]";
	$pos1 = strpos($content, $DelimStart);
	$pos2 = strpos($content, $DelimEnd);
	
	while ($pos1 && $pos2) {
		$DelimLength = strlen($DelimStart);
		$TitleString = substr($content, $pos1 + $DelimLength, ($pos2 - $pos1) - $DelimLength);

		if ('' == $user_ID) {		// NOT Logged In
			$StringReplace = $DelimStart . $TitleString . $DelimEnd;
			$content = str_replace($StringReplace, " <span style=\"color:$PrivateColor;\">[Private Content Removed]</span> ", $content);
			$pos1 = strpos($content, $DelimStart);
			$pos2 = strpos($content, $DelimEnd);
		} else {		// Logged In
			$StringReplace = $DelimStart . $TitleString . $DelimEnd;
			$content = str_replace($StringReplace, " <div style=\"color:$PrivateColor;\">" . $TitleString . "</div> ", $content);
			$pos1 = strpos($content, $DelimStart);
			$pos2 = strpos($content, $DelimEnd);
		}

	}
	return $content;
}

function show_private_posts() {
	$result = check_for_privates();
	if ($result) {
		print("<ul>");
		foreach($result as $post) {
			print("<li>" . $post->post_title . " on " . mysql2date('M-d-Y', $post->post_date)  . "</li>");
		}	
		print("</ul>");
	}
}

function check_for_privates() {
	global $wpdb;
	$post_per_page = get_option('posts_per_page');
	$what_to_show = get_option('what_to_show');
	
	if ($what_to_show = 'days') {
		$post_per_page = $post_per_page * -1;
		$sql = 'SELECT post_title, date_format(post_date, \'%m/%d\') as post_date FROM ' . $wpdb->posts . ' WHERE post_status IN ("private") AND post_date > adddate(NOW(), INTERVAL ' . $post_per_page . ' DAY) ORDER BY post_date DESC';
	} else {
		$sql = 'SELECT post_title, date_format(post_date, \'%m/%d\') as post_date FROM ' . $wpdb->posts . ' WHERE post_status IN ("private") ORDER BY post_date DESC LIMIT ' . $post_per_page;
	}
	return get_posts('post_status=private');
}

function privatize_the_title($title)
{
	global $wpdb, $id;
	$PrivateIndicator = stripslashes(get_option('PrivateIndicator'));
	$original_title = $title;
	if (!strstr($_SERVER['PHP_SELF'],'wp-admin')) {
		
		$sql = "SELECT post_status FROM $wpdb->posts WHERE ID = $id AND post_status IN ('private') LIMIT 1";
		$result = $wpdb->get_results($sql);
		if ($result) {
			$title = $original_title . '<strong>' . $PrivateIndicator . '</strong> ';
		} else {
			$title = $original_title;
		}
	} else {
		$title = $original_title;
	}
	return $title;
}

function add_private_manage_panel() {
//    if (function_exists('add_management_page')) {
//add_management_page('PrivatePost', 'PrivatePost', 8, basename(__FILE__), 'private_post_manage_panel');    }
	add_submenu_page('index.php', __('PrivatePost'), __('PrivatePost'), 'manage_options', 'PrivatePost', 'private_post_manage_panel');

 }

function add_private_options_panel() {
//    if (function_exists('add_options_page')) {
//add_options_page('PrivatePost', 'PrivatePost', 8, basename(__FILE__), 'private_post_options_panel');    }
        add_submenu_page('options-general.php', __('PrivatePost Options'), __('PrivatePost'), 'manage_options', 'PrivatePost Options', 'private_post_options_panel');

 }

 
 
function private_post_manage_panel() {
	global $wpdb;
	$MyPrivateCat = get_option('MyPrivateCat');
	$PrivateIndicator = stripslashes(get_option('PrivateIndicator'));
  if (isset($_POST['make_public'])) {
  	$publicize = $_POST['private'];
  	for ($i = 0; $i < count ($publicize); $i++) {
  		$sql = "UPDATE " . $wpdb->posts . " SET post_status = 'publish' WHERE ID = " . $publicize[$i];
  		$wpdb->get_results($sql);
  	}
  	$privatize = $_POST['public'];
  	for ($i = 0; $i < count ($privatize); $i++) {
  		$sql = "UPDATE " . $wpdb->posts . " SET post_status = 'private' WHERE ID = " . $privatize[$i];
  		$wpdb->get_results($sql);
  	}
  	
  	generate_private_rss($publicize);
  	
  	print("<div class=\"updated\"><p><strong>The following posts have been made public:<br/>");
  	for ($i = 0; $i < count ($publicize); $i++) {
  	    $sql = "SELECT post_title FROM " . $wpdb->posts . " WHERE id = " . $publicize[$i] . " LIMIT 1";
    	$result = $wpdb->get_results($sql);
    	foreach($result as $post) {
	  		print(" - " . $publicize[$i] . " - " . $post->post_title . "<br/>");
  		}
   	}
   	print("<br/>&nbsp;<br/>The following posts have been made private:<br/>");
  	for ($i = 0; $i < count ($privatize); $i++) {
  	    $sql = "SELECT post_title FROM " . $wpdb->posts . " WHERE id = " . $privatize[$i] . " LIMIT 1";
    	$result = $wpdb->get_results($sql);
    	foreach($result as $post) {
	  		print(" - " . $privatize[$i] . " - " . $post->post_title . "<br/>");
  		}
   	}
  	print("</strong></p></div> ");
   
	}

	print ("<div class=wrap>
  <form method=\"post\">
    <h2>Manage Private Posts</h2><table width=\"95%\"><tr><th>Make<br>Private</th><th>Make<br>Public</th><th>Edit</th><th>ID</th><th>Post Date</th><th>Post Title</th><th>Post Author</th></tr>");
    if ($MyPrivateCat != 0) {
		$sql = "SELECT p.id AS id, post_title, display_name, date_format(post_date, '%Y/%m/%d') as post_date, post_status FROM $wpdb->posts p, $wpdb->term_taxonomy t_t, $wpdb->term_relationships t_r, $wpdb->users u WHERE u.id = p.post_author AND t_t.taxonomy = 'category' AND t_t.term_taxonomy_id = t_r.term_taxonomy_id AND t_r.object_id  = p.ID AND t_t.term_id = $MyPrivateCat ORDER BY post_date DESC";
	} else {
		$sql = "SELECT p.id AS id, post_title, display_name, date_format(post_date, '%Y/%m/%d') as post_date, post_status FROM $wpdb->posts p, $wpdb->term_taxonomy t_t, $wpdb->term_relationships t_r, $wpdb->users u WHERE u.id = p.post_author AND t_t.taxonomy = 'category' AND t_t.term_taxonomy_id = t_r.term_taxonomy_id AND t_r.object_id  = p.ID ORDER BY post_date DESC";
	}
//print($sql);
	//$sql = 'SELECT id, post_title FROM ' . $wpdb->posts . ' WHERE post_status IN ("private") ORDER BY post_date DESC';
	$result = $wpdb->get_results($sql);
    if ($result) {
		foreach($result as $post) {
			print("<tr>");
			if ($post->post_status == 'publish') {
				print("<td><center><input name=\"public[]\" type=\"checkbox\" value=\"" . $post->id . "\"></center></td><td>&nbsp;</td>");
			} else if ($post->post_status == 'private') {
				print("<td>&nbsp;</td><td><center><input name=\"private[]\" type=\"checkbox\" value=\"" . $post->id . "\"></center></td>");
			}
			print("<td><a href='post.php?action=edit&amp;post=" . $post->id . "' class='edit'>Edit</a></td><td><a href=\"" . get_permalink($post->id) . "\" rel=\"permalink\" TARGET=\"_blank\">" . $post->id . "</a></td><td>" . $post->post_date . "</td><td>" . $post->post_title . "</td><td>" . $post->display_name . "</td></tr>");
		}	
	}
	print("<tr><td colspan=\"7\"><div class=\"submit\"><input type=\"submit\" name=\"make_public\" value=\"Update Status\" /></div>
  	</form>
  	<tr><th colspan=\"7\" align=\"left\"><h2>Partial Private Posts</h2></th></tr>
  	<tr><th>&nbsp;</th><th>&nbsp;</th><th>Edit</th><th>ID</th><th>Post Date</th><th>Post Title</th><th>Post Author</th></tr>");
	
	
	
	$sql = "SELECT DISTINCT(" . $wpdb->posts . ".id) AS id, post_title, display_name, date_format(post_date, '%Y/%m/%d') as post_date, post_status FROM " . $wpdb->posts . " INNER JOIN " . $wpdb->users . " ON " . $wpdb->users . ".id = " . $wpdb->posts . ".post_author WHERE " . $wpdb->posts . ".post_content LIKE \"%[private]%\" ORDER BY post_date DESC";
//	print("$sql");
	$result = $wpdb->get_results($sql);
    if ($result) {
		foreach($result as $post) {
			print("<tr><th>&nbsp;</th><th>&nbsp;</th>");
			print("<td><a href='post.php?action=edit&amp;post=" . $post->id . "' class='edit'>Edit</a></td><td><a href=\"" . get_permalink($post->id) . "\" rel=\"permalink\" TARGET=\"_blank\">" . $post->id . "</a></td><td>" . $post->post_date . "</td><td>" . $post->post_title . "</td><td>" . $post->display_name . "</td></tr>");
		}	
	}
	print ("</table></div>");
}



function private_post_options_panel() {
	global $wpdb;
	add_option('MyPrivateCat', '0', 'Private Category ID', 'yes');
	add_option('PrivateColor', '#FF0000', 'Private Text Color', 'yes');
	add_option('PrivateIndicator', addslashes(' - <strong>Private</strong>'), 'Indicator for Private Posts', 'yes');
	add_option('PrivateRSSFeedLocation', '/home/wordpress/rss-private.rss', 'Indicator for Private Posts', 'yes');

  if (isset($_POST['add_options'])) {
		update_option('MyPrivateCat',$_POST['NewMyPrivateCat']);
		update_option('PrivateIndicator',$_POST['NewPrivateIndicator']);
		update_option('PrivateRSSFeedLocation',$_POST['NewPrivateRSSFeedLocation']);
		update_option('PrivateColor',$_POST['NewPrivateTextColor']);
		
		print("<div class=\"updated\"><p><strong>Updated</strong></p></div>");
	}
	
	$MyPrivateCat = get_option('MyPrivateCat');
	$PrivateColor = get_option('PrivateColor');
	$PrivateIndicator = stripslashes(get_option('PrivateIndicator'));
	$PrivateRSSFeedLocation = get_option('PrivateRSSFeedLocation');
	
	print ("<div class=wrap>
	  <form method=\"post\"><h2>Private Post Options</h2><table width=\"80%\">");
    
	print("<tr><td>My Private Cat:</td><td><select name=\"NewMyPrivateCat\" size=\"1\"><option value=\"None\"> -- None -- </option>");
	$sql = "SELECT t_t.term_id as cat_ID, name as cat_name FROM $wpdb->term_taxonomy t_t, $wpdb->terms t_r WHERE t_t.taxonomy = 'category' AND t_t.term_id = t_r.term_id";
	
	$result = $wpdb->get_results($sql);
	if ($result) {
		foreach($result as $post) {
			print("<option value=\"" . $post->cat_ID . "\"");
			if ($post->cat_ID == $MyPrivateCat) {
				print (" SELECTED ");
			}
			print(">" . $post->cat_name . "</option>");
		}	
	}	
	print("</select></td></tr>
	<tr><td>Private Indicator:</td><td><input name=\"NewPrivateIndicator\" type=\"text\" value=\"" . htmlspecialchars($PrivateIndicator) . "\" size=\"50\"></td></tr>
	<tr><td colspan=\"2\">Example:<br/>'<strong> - Private</strong>'<br/>or<br/>'<strong> - " . htmlspecialchars('<img src="http://www.mysite.com/images/icon_private.gif">') . "</strong>'</td></tr>
	<tr><td>Private Text Color:</td><td><input name=\"NewPrivateTextColor\" type=\"text\" value=\"" . htmlspecialchars($PrivateColor) . "\" size=\"50\"></td></tr>
	<tr><td>RSS Feed Location:</td><td><input name=\"NewPrivateRSSFeedLocation\" type=\"text\" value=\"" . htmlspecialchars($PrivateRSSFeedLocation) . "\" size=\"50\"></td></tr>
	<tr><td colspan=\"2\">Example:<br/>'/home/wordpress/rss-private.rss'<br/></td></tr>
	</table><div class=\"submit\"><input type=\"submit\" name=\"add_options\" value=\"Update Options\" /></div>
  	</form>
	</div>");
}

function generate_private_rss($publicize) {
	global $wpdb;
	$PrivateRSSFeedLocation = get_option('PrivateRSSFeedLocation');
	$RssLang = get_option('rss_language');
	$blogName = get_bloginfo_rss('name');
	$blogURL = get_bloginfo_rss('url');
	$blogDesc = get_bloginfo_rss('description');
	$dateNow = date("F j, Y, g:i a");
	
	$rssFeed = "<?xml version=\"1.0\"?>\n<rss version=\"2.0\">\n";
	
	$rssFeed .= "<channel>\n<title>$blogName</title>\n
	
	  <link> $blogURL </link>
	
	  <description> $blogDesc </description>
	
	  <language> $RssLang </language>
	  
	  <pubDate> $dateNow </pubDate>
	";
	
//	print(count ($publicize));
	
	for ($i = 0; $i < count ($publicize); $i++) {
  		$sql = "SELECT id, post_title, post_content, date_format(post_date, '%Y/%m/%d %H:%i') as post_date FROM " . $wpdb->posts . " WHERE ID = " . $publicize[$i];
  		$result = $wpdb->get_results($sql);
		if ($result) {
			foreach($result as $post) {
				$rssFeed .= "<item>
					<title>" . htmlspecialchars($post->post_title) . "</title>
					<link>". get_permalink($post->id) . "</link>
					<guid isPermaLink=\"true\">". get_permalink($post->id) . "</guid>
					<description>" . htmlentities(htmlspecialchars(strip_tags($post->post_content))) . "</description>
					</item>
					";
			}	
		}
  	}
	
	$rssFeed .= "
		</channel>
	</rss>";
	if ($fp = fopen($PrivateRSSFeedLocation,"w")) {
		fwrite($fp, $rssFeed);
	} else {
		print ("Could not open file");
	}
}

// Edit the many SQL queries that do not have specific filters
// This is almost guaranteed to break some day
function CjB_Query($sql)
{
  global $wpdb, $user_ID;

  
  //print_r($user_ID);
  
  // Hack, for now
  if (CjB_query_match($sql) && '' != $user_ID) 		// NOT Logged In
  {
    // Collect the cleanup hacks in one place ...
//    $sql = postlevels_query_cleanup($sql);
  
    // Add the join
    $sql = preg_replace("/([\s|,]){$wpdb->posts}([\s|,])/", 
                       "$1({$wpdb->posts} LEFT JOIN {$wpdb->postmeta} as pl_{$wpdb->postmeta} ON ({$wpdb->posts}.ID = pl_{$wpdb->postmeta}.post_id))$2", 
                       $sql);
    
    // Modify the where clause
    $sql = preg_replace("/({$wpdb->posts}\.)?post_status[\s]*=[\s]*[\'|\"]publish[\'|\"]/", " ({$wpdb->posts}.post_status = 'publish' OR ({$wpdb->posts}.post_status = 'private'))", $sql);
    
    // Check for distinct
    if (strpos(strtoupper($sql), "DISTINCT") === false)
    {
      $sql = str_replace("{$wpdb->posts}.*", "DISTINCT {$wpdb->posts}.*", $sql);
      $sql = preg_replace("/[\s]\*/", " DISTINCT {$wpdb->posts}.*", $sql);
    }
    
    
  }
  
  return $sql;
}

// Tells us whether or not we should edit the query
function CjB_query_match($sql)
{
  global $wpdb;
  return ((preg_match("/post_status[\s]*=[\s]*[\'|\"]publish[\'|\"]/", $sql)) && (preg_match("/[\s|,]{$wpdb->posts}[\s|,]/", $sql)));
}
?>
