<?php
/* 
 * Plugin Name:   WP Customer Reviews
 * Version:       1.0.5
 * Plugin URI:    http://www.gowebsolutions.com/plugins/wp-customer-reviews/
 * Description:   WP Customer Reviews allows your customers and visitors to leave reviews or testimonials of your services. Reviews are Microformat enabled (hReview).
 * Author:        Go Web Solutions
 * Author URI:    http://www.gowebsolutions.com/
 *
 * License:       GNU General Public License
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 * 
 */

class WPCustomerReviews
{
    var $plugin_name = 'WP Customer Reviews';
    var $plugin_version = '1.0.5';
    var $dbtable = 'wpcreviews';
    var $path = 'wp-customer-reviews';
    var $wpversion = '';
    var $options = array();
    var $wpurl = '';
    var $got_page_reviews = false;
    var $shown_it = false;

    function WPCustomerReviews() {
        global $wp_version, $table_prefix;
        $this->dbtable = $table_prefix.$this->dbtable;
        $this->wpversion = $wp_version;
        $this->wpurl = get_bloginfo('wpurl');

        $this->get_options(); // populate the options array
        $this->check_migrate(); // call on every instance to see if we have upgraded in any way

        if (!isset($_REQUEST['noheader'])) {
            add_action('admin_head', array(&$this, 'insert_rating_css'));
        }
		
		add_action('admin_menu', array(&$this, 'addmenu'));
        add_action('wp_head', array(&$this, 'insert_rating_css'));
        add_filter('the_content', array(&$this, 'show_reviews'), 1);
        add_filter('the_content', array(&$this, 'aggregate_footer'), 1);
    }

    function activate() {
        global $wpdb;

        $existing_tbl = $wpdb->get_var("SHOW TABLES LIKE '$this->dbtable'");            
        if ( $existing_tbl != $this->dbtable ) {
            $this->createReviewtable();
            return true;
        }

        return false;
    }

    function get_options() { 
        $home_domain = @parse_url(get_home_url());
        $home_domain = $home_domain['scheme']."://".$home_domain['host'].'/';
        
        $default_options = array(
            'act_email' => '',
            'activate' => 0,
            'business_city' => '',
            'business_country' => 'USA',
            'business_email' => get_bloginfo('admin_email'),
            'business_name' => get_bloginfo('name'),
            'business_phone' => '',
            'business_state' => '',
            'business_street' => '',
            'business_url' => $home_domain,
            'business_zip' => '',
            'dbversion' => '0',
            'goto_leave_text' => 'Click here to submit your review.',
            'leave_text' => 'Submit your review',
            'selected_pageid' => -1,
            'show_aggregate_on' => 1,
            'show_hcard_on' => 1,
            'submit_button_text' => 'Submit your review',
            'support_us' => 1,
            'version' => $this->plugin_version
        );
        $this->options = get_option('wpcr_options',$default_options);
        
        // used for migrations to newer versions
        $has_new = false;
        foreach ($default_options as $col => $def_val) {
            if (!isset($this->options[$col])) {
                $this->options[$col] = $def_val;
                $has_new = true;
            }
        }
        
        if ($has_new) { update_option('wpcr_options', $this->options); }
    }
    
    function check_migrate() {
        global $wpdb;
        $migrated = false;
        
        // remove me after official release
        $this->options['dbversion'] = intval(str_replace('.','',$this->options['dbversion']));
        $plugin_db_version = intval(str_replace('.','',$this->plugin_version));
        
        if ($this->options['dbversion'] == $plugin_db_version) { return false; }
        
        // initial installation
        if ($this->options['dbversion'] == 0) { 
            $this->options['dbversion'] = $this->plugin_version;
            update_option('wpcr_options', $this->options);
            return false;
        }
        
        // check for upgrades if needed
        
        // upgrade to 1.0.1
        if ($this->options['dbversion'] < 101) {
            $wpdb->query("ALTER TABLE `$this->dbtable` ADD `trash` TINYINT( 1 ) NOT NULL DEFAULT '0', ADD INDEX ( `trash` )");
            $this->options['dbversion'] = 101;
            update_option('wpcr_options', $this->options);
            $migrated = true;
        }
        
        // done with all migrations, push db flag to newest version
        if ($this->options['dbversion'] != $plugin_db_version || $migrated == true) {
            $this->options['dbversion'] = $plugin_db_version;
            update_option('wpcr_options', $this->options);
            $this->notify_activate($updated_options['act_email'],3); 
            return true;
        }
        
        return false;
    }

    function createReviewTable() {	
        require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );        
        dbDelta("CREATE TABLE IF NOT EXISTS `$this->dbtable` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `date_time` datetime NOT NULL,
                  `reviewer_name` varchar(50) DEFAULT NULL,
                  `reviewer_email` varchar(50) DEFAULT NULL,
                  `reviewer_ip` varchar(15) DEFAULT NULL,
                  `review_title` varchar(150) DEFAULT NULL,
                  `review_text` text,
                  `status` tinyint(1) DEFAULT '0',
                  `review_rating` tinyint(2) DEFAULT '0',
                  `reviewer_url` varchar(255) NOT NULL,
                  PRIMARY KEY (`id`),
                  KEY `status` (`status`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;");
    }

    function addmenu() {
        $this->activate(); // call activate function on every instance to check if we need hands-off upgrade
        add_options_page('Customer Reviews', '<img src="'.$this->getpluginurl().'star.png" />&nbsp;Customer Reviews', 'manage_options', 'wpcr_options', array(&$this, 'admin_options'));
        add_menu_page('Customer Reviews', 'Customer Reviews', 'edit_others_posts', 'view_reviews', array(&$this, 'admin_view_reviews'), $this->getpluginurl().'star.png', 50); // 50 should be underneath comments
    }

    function admin_view_reviews() {
        if (!current_user_can('edit_others_posts'))
        {
            wp_die( __('You do not have sufficient permissions to access this page.') );
        }
        
        global $wpdb;
        
        $p = new stdClass();
        foreach ($_GET as $c => $v) {
            $p->$c = $v;
        }
        foreach ($_POST as $c => $v) {
            $p->$c = $v;
        }
        
        if (isset($p->action)) {
		
            if (isset($p->r)) {
                $p->r = intval($p->r);

                switch ($p->action) {
                    case 'trashreview';
                        $wpdb->query("UPDATE `$this->dbtable` SET status=2 WHERE id={$p->r} LIMIT 1");
                        break;
                    case 'approvereview';
                        $wpdb->query("UPDATE `$this->dbtable` SET status=1 WHERE id={$p->r} LIMIT 1");
                        break;
                    case 'unapprovereview';
                        $wpdb->query("UPDATE `$this->dbtable` SET status=0 WHERE id={$p->r} LIMIT 1");
                        break;
                }
            }
			
            if (is_array($p->delete_reviews) && count($p->delete_reviews)) {
                
                foreach ($p->delete_reviews as $i => $rid) {
                    $p->delete_reviews[$i] = intval($rid);
                }
				
		if (isset($p->act2)) { $p->action = $p->action2; }
				
                switch ($p->action) {
                    case 'bapprove':
                        $wpdb->query("UPDATE `$this->dbtable` SET status=1 WHERE id IN(".implode(',',$p->delete_reviews).")");
                        break;
                    case 'bunapprove':
                        $wpdb->query("UPDATE `$this->dbtable` SET status=0 WHERE id IN(".implode(',',$p->delete_reviews).")");
                        break;
                    case 'btrash':
                        $wpdb->query("UPDATE `$this->dbtable` SET status=2 WHERE id IN(".implode(',',$p->delete_reviews).")");
                        break;
                }
            }
            
            wp_redirect("?page=view_reviews&review_status={$p->review_status}");
            exit();
        }
        
        if (!isset($p->review_status)) { $p->review_status = 0; }
        $p->review_status = intval($p->review_status);
        
        if ($p->review_status == -1) {
            $sql_where = '-1=-1';
        } else {
            $sql_where = 'status='.$p->review_status;
        }
        
        $p->s_orig = '';
        $and_clause = '';
        if (trim($p->s)) {
            $p->s_orig = trim($p->s);
            $p->s = '%'.$p->s_orig.'%';
            $sql_where = '-1=-1';
            $p->review_status = -1;
            $and_clause = "AND (reviewer_name LIKE %s OR reviewer_email LIKE %s OR reviewer_ip LIKE %s OR review_text LIKE %s OR reviewer_url LIKE %s)";
            $and_clause = $wpdb->prepare($and_clause,$p->s,$p->s,$p->s,$p->s,$p->s);
        }
        
        $query = "SELECT 
            id,
            date_time,
            reviewer_name,
            reviewer_email,
            reviewer_ip,
            review_title,
            review_text,
            review_rating,
            reviewer_url,
            status
            FROM `$this->dbtable` WHERE $sql_where $and_clause ORDER BY id DESC";        
        $reviews = $wpdb->get_results($query);
        
        $pending_count = $wpdb->get_results("SELECT COUNT(*) AS count_pending FROM `$this->dbtable` WHERE status=0");
        $pending_count = $pending_count[0]->count_pending;
        
        $trash_count = $wpdb->get_results("SELECT COUNT(*) AS count_trash FROM `$this->dbtable` WHERE status=2");
        $trash_count = $trash_count[0]->count_trash;
        ?>
        <div class="wrap">
            <div class="icon32" id="icon-edit-comments"><br /></div>
            <h2>Customer Reviews</h2>
            
              <ul class="subsubsub">
                <li class="all"><a <?php if ($p->review_status == -1) { echo 'class="current"'; } ?> href="?page=view_reviews&amp;review_status=-1">All</a> |</li>
                <li class="moderated"><a <?php if ($p->review_status == 0) { echo 'class="current"'; } ?> href="?page=view_reviews&amp;review_status=0">Pending 
                    <span class="count">(<span class="pending-count"><?php echo $pending_count;?></span>)</span></a> |
                </li>
                <li class="approved"><a <?php if ($p->review_status == 1) { echo 'class="current"'; } ?> href="?page=view_reviews&amp;review_status=1">Approved</a> |</li>
                <li class="trash"><a <?php if ($p->review_status == 2) { echo 'class="current"'; } ?> href="?page=view_reviews&amp;review_status=2">Trash</a>
                    <span class="count">(<span class="pending-count"><?php echo $trash_count;?></span>)</span></a>
                </li>
              </ul>

			  <form method="GET" action="" id="search-form" name="search-form">
              <p class="search-box">
				  <?php if ($p->s_orig): ?><span style='color:#c00;font-weight:bold;'>RESULTS FOR: </span><?php endif; ?>
				  <label for="comment-search-input" class="screen-reader-text">Search Reviews:</label> 
				  <input type="text" value="<?php echo $p->s_orig; ?>" name="s" id="comment-search-input" />
				  <input type="hidden" name="page" value="view_reviews" />
				  <input type="submit" class="button" value="Search Reviews" />
              </p>
			  </form>

			  <form method="POST" action="?page=view_reviews&noheader=true" id="comments-form" name="comments-form">
              <input type="hidden" name="review_status" value="<?php echo $p->review_status; ?>" />
              <div class="tablenav">
                <div class="alignleft actions">
				  <select name="action">
					<option selected="selected" value="-1">Bulk Actions</option>
					<option value="bunapprove">Unapprove</option>
					<option value="bapprove">Approve</option>
					<option value="btrash">Move to Trash</option>
				  </select>&nbsp;
				  <input type="submit" class="button-secondary apply" name="act" value="Apply" id="doaction" />
                </div><br class="clear" />
              </div>
			  
              <div class="clear"></div>
              <table cellspacing="0" class="widefat comments fixed">
                <thead>
                  <tr>
                    <th style="" class="manage-column column-cb check-column" id="cb" scope="col"><input type="checkbox" /></th>
                    <th style="" class="manage-column column-author" id="author" scope="col">Author</th>
                    <th style="" class="manage-column column-comment" id="comment" scope="col">Review</th>
                  </tr>
                </thead>

                <tfoot>
                  <tr>
                    <th style="" class="manage-column column-cb check-column" scope="col"><input type="checkbox" /></th>
                    <th style="" class="manage-column column-author" scope="col">Author</th>
                    <th style="" class="manage-column column-comment" scope="col">Review</th>
                  </tr>
                </tfoot>

                <tbody class="list:comment" id="the-comment-list">
                  <?php
                  if (count($reviews) == 0) {
                      ?>
                        <tr><td colspan="3" align="center"><br />There are no reviews yet.<br /><br /></td></tr>
                      <?php
                  }
                  foreach ($reviews as $review)
                  {                      
                      $rid = $review->id;
                      $hash = md5( strtolower( trim( $review->reviewer_email ) ) );
                      $review->review_title = stripslashes($review->review_title);
                      $review->review_text = stripslashes($review->review_text);
                      $review->reviewer_name = stripslashes($review->reviewer_name);
                  ?>
                      <tr class="approved" data-id="<?php echo $rid;?>" id="review-<?php echo $rid;?>">
                        <th class="check-column" scope="row"><input type="checkbox" value="<?php echo $rid;?>" name="delete_reviews[]" /></th>
                        <td class="author column-author">
                            <strong><img width="32" height="32" class="avatar avatar-32 photo" src=
                            "http://1.gravatar.com/avatar/<?php echo $hash; ?>?s=32&amp;d=http%3A%2F%2F1.gravatar.com%2Favatar%2Fad516503a11cd5ca435acc9bb6523536%3Fs%3D32&amp;r=G"
                            alt="" /> <?php echo $review->reviewer_name; ?></strong><br />
                            <a href="<?php echo $review->reviewer_url; ?>"><?php echo $review->reviewer_url; ?></a><br />
                            <a href="mailto:<?php echo $review->reviewer_email; ?>"><?php echo $review->reviewer_email; ?></a><br />
                            <a href="?page=view_reviews&amp;s=<?php echo $review->reviewer_ip; ?>"><?php echo $review->reviewer_ip; ?></a><br />
                            <div style="margin-left:0px;">
                                <ul class="wratingnh <?php echo $this->get_rating_class($review->review_rating); ?>">
                                    <li class="one"><a href="#" title="1 Star">1</a></li>
                                    <li class="two"><a href="#" title="2 Stars">2</a></li>
                                    <li class="three"><a href="#" title="3 Stars">3</a></li>
                                    <li class="four"><a href="#" title="4 Stars">4</a></li>
                                    <li class="five"><a href="#" title="5 Stars">5</a></li>
                                </ul>
                            </div>
                        </td>
                        <td class="comment column-comment">
                          <div id="submitted-on">
                            <a href=
                            "<?php echo trailingslashit(get_permalink($this->options['selected_pageid'])); ?>#review_<?php echo $rid;?>">&nbsp;
                            <?php echo date("Y/m/d \a\\t g:i a",strtotime($review->date_time)); ?></a>
                          </div>
                          <p>
                              <span style='font-size:14px;font-weight:bold;'><?php echo nl2br($review->review_title); ?></span><br /><br />
                              <?php echo nl2br($review->review_text); ?>
                          </p>
                          <div class="row-actions">
                            <span class="approve <?php if ($review->status == 0 || $review->status == 2) { echo 'wpcr_show'; } else { echo 'wpcr_hide'; }?>"><a title="Mark as Approved"
                            href="?page=view_reviews&amp;action=approvereview&amp;r=<?php echo $rid;?>&amp;review_status=<?php echo $p->review_status;?>&amp;noheader=true">
                            Mark as Approved</a>&nbsp;|&nbsp;</span>
                            <span class="unapprove <?php if ($review->status == 1 || $review->status == 2) { echo 'wpcr_show'; } else { echo 'wpcr_hide'; }?>"><a title="Mark as Unapproved"
                            href="?page=view_reviews&amp;action=unapprovereview&amp;r=<?php echo $rid;?>&amp;review_status=<?php echo $p->review_status;?>&amp;noheader=true">
                            Mark as Unapproved</a><?php if ($review->status != 2): ?>&nbsp;|&nbsp;<?php endif; ?></span>
                            <span class="trash <?php if ($review->status == 2) { echo 'wpcr_hide'; } else { echo 'wpcr_show'; }?>"><a title="Move to Trash" 
                            href= "?page=view_reviews&amp;action=trashreview&amp;r=<?php echo $rid;?>&amp;review_status=<?php echo $p->review_status;?>&amp;noheader=true">
                            Move to Trash</a></span>
                          </div>
                        </td>
                      </tr>
                  <?php
                  }
                  ?>
                </tbody>
              </table>

              <div class="tablenav">
                <div class="alignleft actions">
                      <select name="action2">
                            <option selected="selected" value="-1">Bulk Actions</option>
                            <option value="bunapprove">Unapprove</option>
                            <option value="bapprove">Approve</option>
                            <option value="btrash">Move to Trash</option>
                      </select>&nbsp;
                      <input type="submit" class="button-secondary apply" name="act2" value="Apply" id="doaction2" />
                </div><br class="clear" />
              </div>
            </form>

            <div id="ajax-response"></div>
          </div>
        <?php
    }

    function update_options() {
        global $wpdb;
        $msg ='';
        $updated_options = $this->options;
        
        if (isset($_REQUEST['optin'])) {        
            
            if ($_REQUEST['Submit'] == 'OK!') {
                $updated_options['act_email'] = $_REQUEST['email'];
                $this->notify_activate($updated_options['act_email'],1);
            } else {
                $this->notify_activate('',1);
            }
            
            $updated_options['activate'] = 1;
            $msg = 'Thank you. Please configure the plugin below.';
        }
        else
        {
            // quick update of all options needed
            foreach ($_REQUEST as $col => $val) {
                if (isset($this->options[$col])) {
                    $updated_options[$col] = $val;
                }
            }
            
            // some int validation
            $updated_options['selected_pageid'] = intval($_REQUEST['page_dropdown']);
            $updated_options['show_aggregate_on'] = intval($_REQUEST['show_aggregate_on']);
            $updated_options['show_hcard_on'] = intval($_REQUEST['show_hcard_on']);
            $updated_options['support_us'] = intval($_REQUEST['support_wpcr']);
			
            // disable comments, trackbacks on the selected page
            $query = "UPDATE {$wpdb->prefix}posts SET comment_status = 'closed', ping_status = 'closed' WHERE ID = ".$updated_options['selected_pageid'];
            $wpdb->query($query);

            if ($updated_options['show_hcard_on']) {
                if (
                    empty($updated_options['business_name']) ||
                    empty($updated_options['business_url']) ||
                    empty($updated_options['business_email']) ||
                    empty($updated_options['business_street']) ||
                    empty($updated_options['business_city']) ||
                    empty($updated_options['business_state']) ||
                    empty($updated_options['business_zip']) ||
                    empty($updated_options['business_phone'])
                ) {
                    $msg .= "* Notice: You must enter in ALL business information to use the hCard output *<br /><br />";
                    $updated_options['show_hcard_on'] = 0;
                }
        }
			
            $msg .= 'Your settings have been saved.';
        }

        update_option('wpcr_options', $updated_options);

        return $msg;
    }
    
    function show_activation() {
        echo '
        <div class="postbox" style="width:600px;">
            <h3>Notify me of new releases</h3>
            <div style="padding:10px; background:#ffffff;">
                <p style="color:#060;">If you would like to be notified of any critical security updates, please enter your email address below. Your information will only be used for notification of future releases.</p><br />
                <form method="post" action="">
                    <input type="hidden" name="optin" value="1" />
                    <label for="email">Email Address: </label><input type="text" size="32" id="email" name="email" />&nbsp;
                    <input type="submit" class="button-primary" value="OK!" name="Submit" />&nbsp;
                    <input type="submit" class="button-primary" value="No Thanks!" name="Submit" />
                </form>
            </div>
        </div>';
    }
	
    function my_get_pages() {
        global $wpdb;
        // gets pages, even if hidden using a plugin
        
        $res = $wpdb->get_results("select ID, post_title from ". $wpdb->posts ." where post_status = 'publish' and post_type = 'page' order by ID");
        return $res;
    }
    
    function show_options() {
        $pages = $this->my_get_pages();
        $selopt = '';
        foreach ($pages as $page) {
            $selected = '';
            if ($page->ID == $this->options['selected_pageid']) { $selected = ' selected'; }
            $selopt .= '<option'.$selected.' value="'.$page->ID.'">'.$page->post_title.'</option>';
        }

        $su_checked = '';
        if ($this->options['support_us']) {
            $su_checked = ' checked';
        }

        echo '
        <div class="postbox" style="width:600px;">
            <h3>Display Options</h3>
            <div id="wpcr_ad">
                <form method="post" action="">
					<div style="background:#eaf2fa;padding:6px;border-top:1px solid #ccc;border-bottom:1px solid #ccc;">
						<legend>Business Information (for hidden hCard)</legend>
					</div>
					<div style="padding:10px;">
						<label for="show_hcard_on">Enable (hidden) Business hCard output on: </label>
						<select id="show_hcard_on" name="show_hcard_on">
							<option ';if ($this->options['show_hcard_on'] == 1) { echo "selected"; } echo ' value="1">All wordpress posts &amp; pages</option>
							<option ';if ($this->options['show_hcard_on'] == 2) { echo "selected"; } echo ' value="2">Homepage &amp; review page</option>
							<option ';if ($this->options['show_hcard_on'] == 3) { echo "selected"; } echo ' value="3">Only the review page</option>
							<option ';if ($this->options['show_hcard_on'] == 0) { echo "selected"; } echo ' value="0">Never</option>
						</select><br />
						<small>This will enable (hidden) the hCard microformat, which includes your business contact information. This is recommended to enable for ALL pages.</small>
						<br /><br />
						<label for="business_name">Business Name (<span style="color:#c00;">Required</span>): </label><input style="width:250px;" type="text" id="business_name" name="business_name" value="'.$this->options['business_name'].'" />
						<br />
						<small>This business name is also used for the required "Product Name" in the review microformat. This is why it is required.</small>
						<br /><br />
						<label for="business_url">Business URL: </label><input style="width:350px;" type="text" id="business_url" name="business_url" value="'.$this->options['business_url'].'" />
						<br /><br />
						<label for="business_email">Business Email: </label><input style="width:250px;" type="text" id="business_email" name="business_email" value="'.$this->options['business_email'].'" />
						<br /><br />
						<label for="business_street">Business Street Address: </label><input style="width:320px;" type="text" id="business_street" name="business_street" value="'.$this->options['business_street'].'" />
						<br /><br />
						<label for="business_city">City: </label><input style="width:150px;" type="text" id="business_city" name="business_city" value="'.$this->options['business_city'].'" />
						&nbsp;
						<label for="business_state">State (2 letters): </label><input style="width:40px;" type="text" id="business_state" name="business_state" value="'.$this->options['business_state'].'" />
						&nbsp;
						<label for="business_zip">Zip Code: </label><input style="width:60px;" type="text" id="business_zip" name="business_zip" value="'.$this->options['business_zip'].'" />
						<br /><br />
						<label for="business_country">Country: </label><input style="width:100px;" type="text" id="business_country" name="business_country" value="'.$this->options['business_country'].'" />
						&nbsp;
						<label for="business_phone">Phone # (555-555-5555): </label><input style="width:120px;" type="text" id="business_phone" name="business_phone" value="'.$this->options['business_phone'].'" />
						<br />
					</div>
					<div style="background:#eaf2fa;padding:6px;border-top:1px solid #ccc;border-bottom:1px solid #ccc;">
						<legend>Review Page Settings</legend>
					</div>
					<div style="padding:10px;padding-bottom:0px;">
						<label for="page_dropdown">Select an existing page to be used for reviews: </label>
						<select id="page_dropdown" name="page_dropdown">
							<option value="-1">Select a page</option>
							'.$selopt.'
						</select><br />
						<small>The reviews and review form will be displayed below any content on the selected page.</small>
						<br /><br />
						<label for="show_aggregate_on">Show aggregate reviews on which pages: </label>
						<select id="show_aggregate_on" name="show_aggregate_on">
							<option ';if ($this->options['show_aggregate_on'] == 1) { echo "selected"; } echo ' value="1">Homepage &amp; review page</option>
							<option ';if ($this->options['show_aggregate_on'] == 2) { echo "selected"; } echo ' value="2">Only the review page</option>
						</select><br />
						<small>This enables the aggregate (rollup) format of all combined reviews. It is recommended to use this on both the Homepage and your review page.</small>
						<br /><br />
						<label for="leave_text">Text to be displayed above review form: </label><input style="width:250px;" type="text" id="leave_text" name="leave_text" value="'.$this->options['leave_text'].'" />
						<br />
						<small>This will be shown as a heading immediately above the review form.</small>
						<br /><br />
						<label for="goto_leave_text">Text to use to link to review form: </label><input style="width:250px;" type="text" id="goto_leave_text" name="goto_leave_text" value="'.$this->options['goto_leave_text'].'" />
						<br />
						<small>This link will be shown above the first review.</small>
						<br /><br />
						<label for="submit_button_text">Text to use for review form submit button: </label><input style="width:150px;" type="text" id="submit_button_text" name="submit_button_text" value="'.$this->options['submit_button_text'].'" />
						<br /><br />
                                                <input id="support_wpcr" name="support_wpcr" type="checkbox"'.$su_checked.' value="1" />&nbsp;<label for="support_wpcr"><small>Support our work and keep this plugin free. By checking this box, a "Powered by WP Customer Reviews" link will be placed at the bottom of your reviews page.</small></label>
						<br />
                                                <div class="submit"><input type="submit" class="button-primary" value="Save Changes" name="Submit"></div>
					</div>
                </form>
                <br />
            </div>
        </div>';
    }

    function admin_options() { 
        if (!current_user_can('manage_options'))
        {
            wp_die( __('You do not have sufficient permissions to access this page.') );
        }

        $msg = '';
        
        if ($_REQUEST['Submit'] == 'Save Changes')
        {
            $msg = $this->update_options();
            $this->get_options();
        }
        
        if (isset($_REQUEST['email'])) {
            $msg = $this->update_options();
            $this->get_options();
        }
        
        echo '
        <div class="wrap">
            <h2>WP Customer Reviews - Options</h2>';
            if ($msg) { echo '<h3 style="color:#a00;">'.$msg.'</h3>'; }
            echo '
            <div class="metabox-holder">
            <div class="postbox" style="width:600px;">
                <h3 style="cursor:default;">About WP Customer Reviews</h3>
                <div style="padding:0 10px; background:#ffffff;">
                    <p>
                        Version: <strong>'.$this->plugin_version.'</strong><br /><br />
                        WP Customer Reviews allows your customers and visitors to leave reviews or testimonials of your services. Reviews are Microformat enabled and can help crawlers such as Google Local Search and Google Places to index these reviews. The plugin also allows for your business information, in hCard microformat, to be (non-visibly) added to all pages.
                    </p>
                </div>
                <div style="padding:6px; background:#eaf2fa;">
                    If you have any questions, please leave feedback at:<br /><a target="_blank" href="http://www.gowebsolutions.com/plugins/wp-customer-reviews/">http://www.gowebsolutions.com/plugins/wp-customer-reviews/</a>
                </div>
            </div>';
        
        if ($this->options['activate'] == 0) {
            $this->show_activation();
            echo '<br /></div>';
            return;
        }

        $this->show_options();
        echo '<br /></div>';
    }
    
    function get_active_reviews() {
        if ($this->got_page_reviews != false) { return $this->got_page_reviews; }
        
        global $wpdb;
        $reviews = $wpdb->get_results( "SELECT 
            id,
            date_time,
            reviewer_name,
            reviewer_email,
            review_title,
            review_text,
            review_rating,
            reviewer_url
            FROM `$this->dbtable` WHERE status=1 ORDER BY id DESC
            " );

        $this->got_page_reviews = $reviews;
        return $this->got_page_reviews;
    }
    
    function aggregate_footer($output) {
        global $post;
        
        $output .= $this->output_aggregate('');
        
        if ($this->options['show_hcard_on'] && !$this->shown_it) {
            $output .= '
            <div id="wpcr-hcard" class="vcard" style="display:none;">
                 <a class="url fn org" href="'.$this->options['business_url'].'">'.$this->options['business_name'].'</a>
                 <a class="email" href="mailto:'.$this->options['business_email'].'">'.$this->options['business_email'].'</a>
                 <div class="adr">
                      <div class="street-address">'.$this->options['business_street'].'</div>
                      <span class="locality">'.$this->options['business_city'].'</span>,
                      <span class="region">'.$this->options['business_state'].'</span>,
                      <span class="postal-code">'.$this->options['business_zip'].'</span>
                      <span class="country-name">'.$this->options['business_country'].'</span>
                 </div>
                 <div class="tel">'.$this->options['business_phone'].'</div>
            </div>
            ';
        }
        
        $this->shown_it = true;
        
        echo $output;
    }
    
    function output_aggregate($reviews_contents) {        
        if ($this->options['show_aggregate_on'] == 2 || $this->shown_it) { return ''; }
        
        $reviews = $this->get_active_reviews();
        
        $summary = '';
        $scores = array();
        if (count($reviews) == 0) {
            $scores[] = 0;
        } else {            
            foreach ($reviews as $review)
            {
                if ($summary == '') { $summary = substr($review->review_text,0,180); }
                $scores[] = $review->review_rating;
            }
        }
        
        $best_score = number_format(max($scores),1);
        $average_score = number_format(array_sum($scores) / count($scores),1);
        
        $content .= '
        <div class="hreview-aggregate" id="hreview-wpcr-aggregate">
           <div style="display:none;">
                <div class="item vcard" id="hreview-wpcr-vcard">
                    <a class="url fn org" href="'.$this->options['business_url'].'">'.$this->options['business_name'].'</a>
                    <div class="tel">'.$this->options['business_phone'].'</div>
                    <div class="adr">
                        <div class="street-address">'.$this->options['business_street'].'</div>
                        <span class="locality">'.$this->options['business_city'].'</span>
                        <span class="region">'.$this->options['business_state'].'</span>, <span class="postal-code">'.$this->options['business_zip'].'</span>
                        <div class="country-name">'.$this->options['business_country'].'</div>
                    </div>
                </div>
               <div class="rating">
                 <span class="average">'.$average_score.'</span>
                 <span class="best">'.$best_score.'</span>
               </div>  
               <span class="votes">'.count($scores).'</span>
               <span class="count">'.count($scores).'</span>
               <span class="summary">'.$summary.'</span>
           </div>
        </div>
        ';
        
        $content .= $reviews_contents;
        
        $this->shown_it = true;
        
        return $content;
    }
    
    function iso8601($time=false) {
        if ($time === false) $time = time();
        $date = date('Y-m-d\TH:i:sO', $time);
        return (substr($date, 0, strlen($date)-2).':'.substr($date, -2));
    }
    
    function insert_rating_css($output) {	
        $output .= '
        <style type="text/css">
		.wpcr_show { display:inline; }
        .wpcr_hide { display:none; }
        .awpcrform { display:block;height:1px;width:1px; }
        #commentform #confirm1,#commentform #confirm3 { display:none; }
		#wpcr_ad { background:#ffffff; }
		#wpcr_ad label { font-weight:bold; }
		#wpcr_submit_btn,#commentform #confirm2 { width:auto !important; }

        .wrating, .wratingnh{
            width:80px;
            height:16px;
            margin:4px 0 0 !important;
            padding:0;
            list-style:none;
            clear:both;
            float:left;
            position:relative;
            background: url('.$this->getpluginurl().'star-matrix.gif) no-repeat 0 0;
        }
        .nostar {background-position:0 0}
        .onestar {background-position:0 -16px}
        .twostar {background-position:0 -32px}
        .threestar {background-position:0 -48px}
        .fourstar {background-position:0 -64px}
        .fivestar {background-position:0 -80px}
        ul.wrating li, ul.wratingnh li {
            cursor: pointer;
            /*ie5 mac doesnt like it if the list is floated\*/
            float:left;
            /* end hide*/
            text-indent:-999em;
        }
        ul.wrating li a, ul.wratingnh li a {
            position:absolute;
            left:0;
            top:0;
            width:16px;
            height:16px;
            text-decoration:none;
            z-index: 200;
            outline:none;
        }
        ul.wrating li.one a, ul.wratingnh li.one a {left:0}
        ul.wrating li.two a, ul.wratingnh li.two a {left:16px;}
        ul.wrating li.three a, ul.wratingnh li.three a {left:32px;}
        ul.wrating li.four a, ul.wratingnh li.four a {left:48px;}
        ul.wrating li.five a, ul.wratingnh li.five a {left:64px;}
        ul.wrating li a:hover {
            z-index:2;
            width:80px;
            height:16px;
            overflow:hidden;
            left:0;	
            background: url('.$this->getpluginurl().'star-matrix.gif) no-repeat 0 0
        }
        ul.wrating li.one a:hover {background-position:0 -96px;}
        ul.wrating li.two a:hover {background-position:0 -112px;}
        ul.wrating li.three a:hover {background-position:0 -128px}
        ul.wrating li.four a:hover {background-position:0 -144px}
        ul.wrating li.five a:hover {background-position:0 -160px}
        </style>';
        
        echo $output;
    }

    function show_reviews($the_content) {
        global $post;

        if ($this->options['selected_pageid'] != $post->ID) { return $the_content; }
        
        $msg = '';
        if ($_POST['submitwpcr_'.$post->ID] == $this->options['submit_button_text']) {
            $msg = $this->add_review();
        }
        
        if ($msg) {
            $the_content .= $msg;
        }
        
        $the_content .= '<p><a href="#wpcrform">'.$this->options['goto_leave_text'].'</a></p><hr />';
        
        $reviews = $this->get_active_reviews();
        
        $reviews_content = '';
        
        $scores = array();
        if (count($reviews) == 0) {
            $the_content .= '<p>There are no reviews yet. Be the first to leave yours!</p>';
            $scores[] = 0;
        } else {            
            foreach ($reviews as $review)
            {
                $reviews_content .= '
                <div id="review_'.$review->id.'">
                    <div class="hreview" id="hreview-'.$review->id.'">
                        <h2 class="summary">'.$review->review_title.'</h2>
                        <div style="float:left;padding-right:10px;">
                            <ul class="wratingnh '.$this->get_rating_class($review->review_rating).'">
                                <li class="one"><a href="#" title="1 Star">1</a></li>
                                <li class="two"><a href="#" title="2 Stars">2</a></li>
                                <li class="three"><a href="#" title="3 Stars">3</a></li>
                                <li class="four"><a href="#" title="4 Stars">4</a></li>
                                <li class="five"><a href="#" title="5 Stars">5</a></li>
                            </ul>
                        </div>
                        <div style="float:left;">
                            <abbr title="'.$this->iso8601(strtotime($review->date_time)).'" class="dtreviewed">'.date("M d, Y",strtotime($review->date_time)).'</abbr> by <span class="reviewer vcard" id="hreview-wpcr-reviewer-'.$review->id.'"><span class="fn">'.$review->reviewer_name.'</span></span>
                        </div>
                        <div style="clear:both;"></div>
                        <span style="display:none;" class="type">business</span>
                        <div class="item vcard" id="hreview-wpcr-hcard-for-'.$review->id.'" style="display:none;">
                            <a class="url fn org" href="'.$this->options['business_url'].'">'.$this->options['business_name'].'</a>
                            <div class="tel">'.$this->options['business_phone'].'</div>
                            <div class="adr">
                                <div class="street-address">'.$this->options['business_street'].'</div>
                                <span class="locality">'.$this->options['business_city'].'</span>
                                <span class="region">'.$this->options['business_state'].'</span>, <span class="postal-code">'.$this->options['business_zip'].'</span>
                                <div class="country-name">'.$this->options['business_country'].'</div>
                            </div>
                        </div>
                        <blockquote class="description">
                            <p>
                                <abbr class="rating" title="'.$review->review_rating.'"></abbr>
                                '.$review->review_text.'
                            </p>
                        </blockquote>
                        <span style="display:none;" class="version">0.3</span>
                   </div>
                </div>
                <hr />';
                
                $scores[] = $review->review_rating;
            }
        }
        
        $the_content .= $this->output_aggregate($reviews_content);   
        $the_content .= $this->show_reviews_form();
        $the_content .= '<p style="padding-top:30px;">Powered by <strong><a href="http://www.gowebsolutions.com/plugins/wp-customer-reviews/">WP Customer Reviews</a></strong></p>';

        return $the_content;
    }
    
    function get_rating_class($rating) {
        if ($rating == 1) { return 'onestar'; }
        if ($rating == 2) { return 'twostar'; }
        if ($rating == 3) { return 'threestar'; }
        if ($rating == 4) { return 'fourstar'; }
        if ($rating == 5) { return 'fivestar'; }
        return 'onestar';
    }
    
    function show_reviews_form() {
        global $post;
        
        $script = '<script type="text/javascript">                    
                    function valwpcrform(me) {	
						var frating = parseInt(jQuery("#frating").val());
						if (!frating) { frating = 0; }
					
						if (jQuery("#wpcr_fname").val() == "") {
							alert("You must include your name.");
                            return false;
						}
                        if (jQuery("#confirm2").is(":checked") == false) {
                            alert("You must confirm that you are human.");
                            return false;
                        }
                        if (jQuery("#confirm1").is(":checked") || jQuery("#confirm3").is(":checked")) {
                            alert("You must confirm that you are human. Code 2.");
                            return false;
                        }						
						if (frating < 1 || frating > 5) {
							alert("Please select a star rating from 1 to 5.");
                            return false;
						}
						
                        jQuery(me).attr("action","");
                        return true;
                    };
                    
                    jQuery(".wrating a").live("click",function() {
                        var p = jQuery(this).parent().parent();
                        p.removeClass("nostar");
                        p.removeClass("onestar");
                        p.removeClass("twostar");
                        p.removeClass("threestar");
                        p.removeClass("fourstar");
                        p.removeClass("fivestar");
                        
                        var wpcr_rating = jQuery(this).html();
                        jQuery("#frating").val(wpcr_rating);
                        if (wpcr_rating == 1) { p.addClass("onestar"); }
                        if (wpcr_rating == 2) { p.addClass("twostar"); }
                        if (wpcr_rating == 3) { p.addClass("threestar"); }
                        if (wpcr_rating == 4) { p.addClass("fourstar"); }
                        if (wpcr_rating == 5) { p.addClass("fivestar"); }
                        return false;
                    });
                </script>';
        $script = str_replace(array("\r","\n","\t","  "),array('','','',''),$script);
        
        return '
            <div id="respond">
                <a id=\'wpcrform\' class=\'awpcrform\'></a>
                <h4 id=\'postcomment\'>'.$this->options['leave_text'].'</h4>
                <form onsubmit=\'return valwpcrform(this);\' class=\'wpcrcform\' id=\'commentform\' method="post" action="'.trailingslashit($this->wpurl).'nospam/">
                    <p><label for="wpcr_fname" class="comment-field"><small>Name:</small> <input class="text-input" type="text" id="wpcr_fname" name="fname" value="'.$_POST['name'].'" /></label></p>
                    <p><label for="femail" class="comment-field"><small>Email:</small> <input class="text-input" type="text" id="femail" name="femail" value="'.$_POST['email'].'" /></label></p>
                    <p><label for="fwebsite" class="comment-field"><small>Website:</small> <input class="text-input" type="text" id="fwebsite" name="fwebaddy" value="'.$_POST['webaddy'].'" /></label></p>
                    <p><label for="ftitle" class="comment-field"><small>Review Title:</small> <input class="text-input" type="text" id="ftitle" name="ftitle" value="'.$_POST['title'].'" /></label></p>
                    <div><div style="float:left;"><span class="comment-field"><small>Rating:</small></span></div>&nbsp;<div style="margin-left:5px;float:left;display:inline;">
                            <ul class="wrating nostar">
                                <li class="one"><a href="#" title="1 Star">1</a></li>
                                <li class="two"><a href="#" title="2 Stars">2</a></li>
                                <li class="three"><a href="#" title="3 Stars">3</a></li>
                                <li class="four"><a href="#" title="4 Stars">4</a></li>
                                <li class="five"><a href="#" title="5 Stars">5</a></li>
                            </ul>
                        </div>
                        <div style="clear:both;"></div>
                        <input type="hidden" id="frating" name="frating" value="'.$_POST['frating'].'" />
                    </div>
                    <p><label for="ftext" class="comment-field"><small>Review:</small></label></p>
                    <div>
                        <textarea id="ftext" name="ftext" cols="50" rows="10">'.$_POST['text'].'</textarea><br />
                    </div>
					<div style="margin-top:10px;">
						<div style="font-size:13px;color:#c00;margin-bottom:4px;">
							<input type="checkbox" name="fconfirm1" id="confirm1" value="1" />
							<input type="checkbox" name="fconfirm2" id="confirm2" value="1" />&nbsp;<label for="confirm2">Check this box to confirm you are human.</label>
							<input type="checkbox" name="fconfirm3" id="confirm3" value="1" />
						</div>
						<input id="wpcr_submit_btn" name="submitwpcr_'.$post->ID.'" type="submit" id="submit" value="'.$this->options['submit_button_text'].'" />
					</div>
					<div style="clear:both;"></div>
                </form>
                '.$script.'
            </div>
        ';
    }

    function add_review() {
        global $wpdb;
        
        $p = new stdClass();
        foreach ($_POST as $c => $v) {
            $p->$c = trim( stripslashes( $v ) );
        }
        
        $errors = '';
        // server-side validation
        if (intval($p->fconfirm1) == 1 || intval($p->fconfirm3) == 1) {
            $errors .= 'You have triggered our anti-spam system. Please try again. Code 001.';
        }
        
        if (intval($p->fconfirm2) != 1) {
            $errors .= 'You have triggered our anti-spam system. Please try again. Code 002';
        }
		
		$p->frating = intval($p->frating);
		if ($p->frating < 1 || $p->frating > 5) {
			$errors .= 'You have triggered our anti-spam system. Please try again. Code 003';
		}
		
		if (trim($p->fname) == '') {
			$errors .= 'Your name is required. Please try again. Code 004';
		}
        
        if ($errors) { return '<div style="color:#c00;font-weight:bold;padding-bottom:15px;">'.$errors.'</div>'; }
        
        // some sanitation
        $date_time = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'];        
        $p->fname = strip_tags($p->fname);
        $p->ftitle = strip_tags($p->ftitle);
        $p->ftext = strip_tags($p->ftext);
        $p->frating = intval($p->frating);
        
        $query = $wpdb->prepare("INSERT INTO `$this->dbtable` 
                (date_time, reviewer_name, reviewer_email, reviewer_ip, review_title, review_text, status, review_rating, reviewer_url) 
                VALUES (%s, %s, %s, %s, %s, %s, %d, %d, %s)",
                $date_time, $p->fname, $p->femail, $ip, $p->ftitle, $p->ftext, 0, $p->frating, $p->fwebaddy);
        
        $wpdb->query($query);
        
        @wp_mail( get_bloginfo('admin_email'), "WP Customer Reviews: New Review Posted on ".date('m/d/Y h:i'), "A new review has been posted for ".$this->options['business_name']." via WP Customer Reviews. \n\nYou will need to login to the admin area and approve this review before it will appear on your site.");
        
        return '<div style="color:#c00;font-weight:bold;padding-bottom:15px;padding-top:15px;">Thank you for your comments. All reviews are moderated and if approved, yours will appear soon.</div>';
    }
    
    function deactivate() {
        global $wp_version;

        if ($this->options['activate'] == 0) { return; }
        
        $updated_options = $this->options;
        $updated_options['activate'] = 0;
        update_option('wpcr_options', $updated_options);
        $this->notify_activate($updated_options['act_email'],2);
    }

    function notify_activate($email,$act_flag) {  
        global $wp_version;
        
        $request = 'doact='.$act_flag.'&email='.urlencode(stripslashes($email)).'&version='.$this->plugin_version.'&support='.$this->options['support_us'];
        $host = "www.gowebsolutions.com";
        $port = 80;
        
        $http_request  = "POST /plugin-activation/activate.php HTTP/1.0\r\n";
		$http_request .= "Host: www.gowebsolutions.com\r\n";
		$http_request .= "Content-Type: application/x-www-form-urlencoded; charset=".get_option('blog_charset')."\r\n";
		$http_request .= "Content-Length: ".strlen($request)."\r\n";
        $http_request .= "Referer: $this->wpurl\r\n";
		$http_request .= "User-Agent: WordPress/$wp_version\r\n\r\n";
		$http_request .= $request;

		$response = '';
		if( false != ( $fs = @fsockopen($host, $port, $errno, $errstr, 10) ) ) {
			fwrite($fs, $http_request);
			while ( !feof($fs) ) {
				$response .= fgets($fs, 1160);
			}
			fclose($fs);
			$response = explode("\r\n\r\n", $response, 2);
		}
			
		return $response;
    }
    
    function set_gotosettings() {
        add_option('wpcr_gotosettings', true);
    }
    
    function redirect_settings() {
        if (get_option('wpcr_gotosettings', false)) {
            delete_option('wpcr_gotosettings');
            
            // get the real wp-admin path, even if renamed
            $admin_path = $_SERVER['REQUEST_URI'];            
            $admin_path = substr($admin_path, 0, stripos($admin_path,'plugins.php'));
            
            $url = $admin_path.'options-general.php?page=wpcr_options';
            wp_redirect($url);
        }
    }
    
    function plugin_settings_link($links) { 
        $settings_link = '<a href="options-general.php?page=wpcr_options"><img src="'.$this->getpluginurl().'star.png" />&nbsp;Settings</a>'; 
        array_unshift($links, $settings_link); 
        return $links; 
    }
    
    function getpluginurl() {
        return trailingslashit(plugins_url(basename(dirname(__FILE__))));
    }
    
    function enqueue_jquery() {
        wp_enqueue_script('jquery');
    }
}

$WPCustomerReviews = new WPCustomerReviews();
register_activation_hook(__FILE__, array( &$WPCustomerReviews, 'set_gotosettings' ));
register_deactivation_hook( __FILE__, array( &$WPCustomerReviews, 'deactivate' ));
add_action('admin_init', array(&$WPCustomerReviews, 'redirect_settings'));
add_action('init', array(&$WPCustomerReviews, 'enqueue_jquery'));
add_filter('plugin_action_links_'.plugin_basename(__FILE__), array(&$WPCustomerReviews, 'plugin_settings_link'));
?>