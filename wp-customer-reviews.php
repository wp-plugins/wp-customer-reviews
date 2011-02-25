<?php
/* 
 * Plugin Name:   WP Customer Reviews
 * Version:       1.2.3
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
    var $plugin_version = '1.2.3';
    var $dbtable = 'wpcreviews';
    var $options = array();
    var $wpurl = '';
    var $got_aggregate = false;
    var $shown_aggregate = false;
    var $p = '';
    var $page = 1;

    function WPCustomerReviews() {
        global $table_prefix;
        $this->dbtable = $table_prefix.$this->dbtable;
		
        add_filter('plugin_action_links_'.plugin_basename(__FILE__), array(&$this, 'plugin_settings_link'));

		add_action('the_content', array(&$this, 'show_reviews'), 5);
        add_action('the_content', array(&$this, 'aggregate_footer'), 5);
		
        add_action('init', array(&$this, 'init'));
        add_action('admin_init', array(&$this, 'admin_init'));
        add_action('admin_menu', array(&$this, 'addmenu'));
        add_action('get_header', array(&$this, 'enqueue_stuff')); /* need to enqueue before wp_head gets called */
        add_action('wp_ajax_update_field', array(&$this, 'admin_view_reviews')); /* special ajax stuff */
    }

    function get_options() { 
        $home_domain = @parse_url(get_home_url());
        $home_domain = $home_domain['scheme']."://".$home_domain['host'].'/';
        
        $default_options = array(
            'act_email' => '',
            'activate' => 0,
            'ask_fields' => array('fname' => 1, 'femail' => 1, 'fwebsite' => 1, 'ftitle' => 1),
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
            'require_fields' => array('fname' => 1, 'femail' => 1, 'fwebsite' => 0, 'ftitle' => 0),
            'reviews_per_page' => 10,
            'selected_pageid' => -1,
            'show_aggregate_on' => 1,
            'show_fields' => array('fname' => 1, 'femail' => 0, 'fwebsite' => 0, 'ftitle' => 1),
            'show_hcard_on' => 1,
            'submit_button_text' => 'Submit your review',
            'support_us' => 1,
            'title_tag' => 'h2'
        );
        $this->options = get_option('wpcr_options',$default_options);
        
        // used for migrations to newer versions
        $has_new = false;
        foreach ($default_options as $col => $def_val) {
            
            if (!isset($this->options[$col])) {
                $this->options[$col] = $def_val;
                $has_new = true;
            }
            
            if (is_array($def_val)) {
                foreach ($def_val as $acol => $aval) {
                    if (!isset($this->options[$col][$acol])) {
                        $this->options[$col][$acol] = $aval;
                        $has_new = true;
                    }
                }
            }
        }
        
        if ($has_new) { update_option('wpcr_options', $this->options); }
    }
    
    function make_p_obj() {
        $this->p = new stdClass();
        
        foreach ($_GET as $c => $val) {
            if (is_array($val)) { 
                $this->p->$c = $val;
            }
            else { $this->p->$c = trim( stripslashes( $val) ); }
        }
        
        foreach ($_POST as $c => $val) {
            if (is_array($val)) { 
                $this->p->$c = $val;
            }
            else { $this->p->$c = trim( stripslashes( $val) ); }
        }
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
            $this->notify_activate($this->options['act_email'],3);
            $this->force_update_cache(); // update any caches
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
    
    function enqueue_stuff($admin_area = false) {
        global $post;
        
        if ($this->options['selected_pageid'] == $post->ID) {
            wp_enqueue_script('jquery');
            wp_register_script('wp-customer-reviews',$this->getpluginurl().'wp-customer-reviews.js',array(),$this->plugin_version);
            wp_enqueue_script('wp-customer-reviews');
                  
            /* do this here so we can redirect */
            if ($admin_area === null || $admin_area === false) {
                $GET_P = "submitwpcr_$post->ID";
                                
                if ($this->p->$GET_P == $this->options['submit_button_text']) {
                    $msg = $this->add_review();

                    $has_error = $msg[0];
                    $status_msg = $msg[1];
                    $cookie = array('wpcr_status_msg' => $status_msg);
                    
					$url = get_permalink($post->ID);
					
					if (headers_sent() == true) {
						$this->js_redirect($url,$cookie); /* use JS redirect and add cookie before redirect */
					} else {
						foreach ($cookie as $col => $val) {
							setcookie($col,$val); /* add cookie via headers */
						}
						ob_end_clean();
						wp_redirect($url); /* nice redirect */
					}
					
					exit();
                }
            }
        }
        
        /* styles needed for hidden hcard so we just include them everywhere */
        wp_register_style('wp-customer-reviews',$this->getpluginurl().'wp-customer-reviews.css',array(),$this->plugin_version);        
        wp_enqueue_style('wp-customer-reviews');
        
        /* some admin styles can override normal styles for inplace edits */
        if ($admin_area) {
            if (isset($this->p->page) && ( $this->p->page == 'wpcr_view_reviews' || $this->p->page == 'wpcr_options' ) ) {
                wp_enqueue_script('jquery');
                wp_register_script('wp-customer-reviews-admin',$this->getpluginurl().'wp-customer-reviews-admin.js',array(),$this->plugin_version);
                wp_enqueue_script('wp-customer-reviews-admin');
                wp_register_style('wp-customer-reviews-admin',$this->getpluginurl().'wp-customer-reviews-admin.css',array(),$this->plugin_version);        
                wp_enqueue_style('wp-customer-reviews-admin');
            }
        }
    }

    function addmenu() {
        add_options_page('Customer Reviews', '<img src="'.$this->getpluginurl().'star.png" />&nbsp;Customer Reviews', 'manage_options', 'wpcr_options', array(&$this, 'admin_options'));
        add_menu_page('Customer Reviews', 'Customer Reviews', 'edit_others_posts', 'wpcr_view_reviews', array(&$this, 'admin_view_reviews'), $this->getpluginurl().'star.png', 50); // 50 should be underneath comments
    }
    
    function admin_view_reviews() {        
        global $wpdb;
        
        /* begin - actions */
        if (isset($this->p->action)) {
		
            if (isset($this->p->r)) {
                $this->p->r = intval($this->p->r);

                switch ($this->p->action) {
                    case 'deletereview':
                        $wpdb->query("DELETE FROM `$this->dbtable` WHERE id={$this->p->r} LIMIT 1");
                        break;
                    case 'trashreview':
                        $wpdb->query("UPDATE `$this->dbtable` SET status=2 WHERE id={$this->p->r} LIMIT 1");
                        break;
                    case 'approvereview':
                        $wpdb->query("UPDATE `$this->dbtable` SET status=1 WHERE id={$this->p->r} LIMIT 1");
                        break;
                    case 'unapprovereview':
                        $wpdb->query("UPDATE `$this->dbtable` SET status=0 WHERE id={$this->p->r} LIMIT 1");
                        break;
                    case 'update_field':
                        
                        ob_end_clean();
                        
                        if (!is_array($this->p->json)) { 
                            header('HTTP/1.1 403 Forbidden');
                            echo json_encode(array("errors" => 'Bad Request'));
                            exit(); 
                        }
                        
                        $show_val = '';
                        $update_col = false;
                        $update_val = false;
                        
                        foreach ($this->p->json as $col => $val) {
                            
                            switch ($col) {
                                case 'date_time':
                                    $d = date("m/d/Y g:i a",strtotime($val));
                                    if (!$d || $d == '01/01/1970 12:00 am') {
                                        header('HTTP/1.1 403 Forbidden');
                                        echo json_encode(array("errors" => 'Bad Date Format'));
                                        exit(); 
                                    }
                                    
                                    $show_val = $d;
                                    $d2 = date("Y-m-d H:i:s",strtotime($val));
                                    $update_col = mysql_real_escape_string($col);
                                    $update_val = mysql_real_escape_string($d2);
                                    break;
                                    
                                default:
                                    if ($val == '') {
                                        header('HTTP/1.1 403 Forbidden');
                                        echo json_encode(array("errors" => 'Bad Value'));
                                        exit(); 
                                    }
									
									$val = str_replace( array("<br />","<br/>","<br>") , "" , $val ); /* remove extra breaks, these should be newlines */
                                    $show_val = $val;
									
									$update_col = mysql_real_escape_string($col);
                                    $update_val = mysql_real_escape_string($val);
                                    break;
                            }
                            
                        }
                        
                        if ($update_col !== false && $update_val !== false) {
                            $query = "UPDATE `$this->dbtable` SET `$update_col`='$update_val' WHERE id={$this->p->r} LIMIT 1";
                            $wpdb->query($query);
                            echo $show_val;
                        }
                        
                        exit();
                        break;
                }
            }
			
            if (is_array($this->p->delete_reviews) && count($this->p->delete_reviews)) {
                
                foreach ($this->p->delete_reviews as $i => $rid) {
                    $this->p->delete_reviews[$i] = intval($rid);
                }
				
                if (isset($this->p->act2)) { $this->p->action = $this->p->action2; }
				
                switch ($this->p->action) {
                    case 'bapprove':
                        $wpdb->query("UPDATE `$this->dbtable` SET status=1 WHERE id IN(".implode(',',$this->p->delete_reviews).")");
                        break;
                    case 'bunapprove':
                        $wpdb->query("UPDATE `$this->dbtable` SET status=0 WHERE id IN(".implode(',',$this->p->delete_reviews).")");
                        break;
                    case 'btrash':
                        $wpdb->query("UPDATE `$this->dbtable` SET status=2 WHERE id IN(".implode(',',$this->p->delete_reviews).")");
                        break;
                    case 'bdelete':
                        $wpdb->query("DELETE FROM `$this->dbtable` WHERE id IN(".implode(',',$this->p->delete_reviews).")");
                        break;
                }
            }
			
            $this->force_update_cache(); // update any caches
		            
            echo $this->js_redirect("?page=wpcr_view_reviews&review_status={$this->p->review_status}");
            exit();
        }
        /* end - actions */
        
        if (!isset($this->p->review_status)) { $this->p->review_status = 0; }
        $this->p->review_status = intval($this->p->review_status);
        
        /* begin - searching */
        if ($this->p->review_status == -1) {
            $sql_where = '-1=-1';
        } else {
            $sql_where = 'status='.$this->p->review_status;
        }
        
        $this->p->s_orig = $this->p->s;
        $and_clause = '';
        if ($this->p->s) { /* searching */
            $this->p->s = '%'.$this->p->s.'%';
            $sql_where = '-1=-1';
            $this->p->review_status = -1;
            $and_clause = "AND (reviewer_name LIKE %s OR reviewer_email LIKE %s OR reviewer_ip LIKE %s OR review_text LIKE %s OR reviewer_url LIKE %s)";
            $and_clause = $wpdb->prepare($and_clause,$this->p->s,$this->p->s,$this->p->s,$this->p->s,$this->p->s);
            
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
            $total_reviews = 0; /* no pagination for searches */
        }
        /* end - searching */
        else
        {
            $arr_Reviews = $this->get_reviews($this->page,$this->options['reviews_per_page'],$this->p->review_status);
            $reviews = $arr_Reviews[0];
            $total_reviews = $arr_Reviews[1];
        }
        
        $pending_count = $wpdb->get_results("SELECT COUNT(*) AS count_pending FROM `$this->dbtable` WHERE status=0");
        $pending_count = $pending_count[0]->count_pending;

        $trash_count = $wpdb->get_results("SELECT COUNT(*) AS count_trash FROM `$this->dbtable` WHERE status=2");
        $trash_count = $trash_count[0]->count_trash;
        ?>
        <div id="wpcr_respond_1" class="wrap">
            <div class="icon32" id="icon-edit-comments"><br /></div>
            <h2>Customer Reviews</h2>
            
              <ul class="subsubsub">
                <li class="all"><a <?php if ($this->p->review_status == -1) { echo 'class="current"'; } ?> href="?page=wpcr_view_reviews&amp;review_status=-1">All</a> |</li>
                <li class="moderated"><a <?php if ($this->p->review_status == 0) { echo 'class="current"'; } ?> href="?page=wpcr_view_reviews&amp;review_status=0">Pending 
                    <span class="count">(<span class="pending-count"><?php echo $pending_count;?></span>)</span></a> |
                </li>
                <li class="approved"><a <?php if ($this->p->review_status == 1) { echo 'class="current"'; } ?> href="?page=wpcr_view_reviews&amp;review_status=1">Approved
					<span class="count">(<span class="pending-count"><?php echo $total_reviews;?></span>)</span></a> |
				</li>
                <li class="trash"><a <?php if ($this->p->review_status == 2) { echo 'class="current"'; } ?> href="?page=wpcr_view_reviews&amp;review_status=2">Trash</a>
                    <span class="count">(<span class="pending-count"><?php echo $trash_count;?></span>)</span></a>
                </li>
              </ul>

              <form method="GET" action="" id="search-form" name="search-form">
                  <p class="search-box">
                      <?php if ($this->p->s_orig): ?><span style='color:#c00;font-weight:bold;'>RESULTS FOR: </span><?php endif; ?>
                      <label for="comment-search-input" class="screen-reader-text">Search Reviews:</label> 
                      <input type="text" value="<?php echo $this->p->s_orig; ?>" name="s" id="comment-search-input" />
                      <input type="hidden" name="page" value="view_reviews" />
                      <input type="submit" class="button" value="Search Reviews" />
                  </p>
              </form>

              <form method="POST" action="?page=wpcr_view_reviews" id="comments-form" name="comments-form">
              <input type="hidden" name="review_status" value="<?php echo $this->p->review_status; ?>" />
              <div class="tablenav">
                <div class="alignleft actions">
                      <select name="action">
                            <option selected="selected" value="-1">Bulk Actions</option>
                            <option value="bunapprove">Unapprove</option>
                            <option value="bapprove">Approve</option>
                            <option value="btrash">Move to Trash</option>
                            <option value="bdelete">Delete Forever</option>
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
                      $update_path = $this->get_admin_path()."admin-ajax.php?page=wpcr_view_reviews&r=$rid&action=update_field";
                      $hash = md5( strtolower( trim( $review->reviewer_email ) ) );
                      $review->review_title = stripslashes($review->review_title);
                      $review->review_text = stripslashes($review->review_text);
                      $review->reviewer_name = stripslashes($review->reviewer_name);
                      if ($review->reviewer_name == '') { $review->reviewer_name = 'Anonymous'; }
					  $review_text = nl2br($review->review_text);
					  $review_text = str_replace( array("\r\n","\r","\n") , "" , $review_text );
                  ?>
                      <tr class="approved" id="review-<?php echo $rid;?>">
                        <th class="check-column" scope="row"><input type="checkbox" value="<?php echo $rid;?>" name="delete_reviews[]" /></th>
                        <td class="author column-author">
                            <img width="32" height="32" class="avatar avatar-32 photo" src=
                            "http://1.gravatar.com/avatar/<?php echo $hash; ?>?s=32&amp;d=http%3A%2F%2F1.gravatar.com%2Favatar%2Fad516503a11cd5ca435acc9bb6523536%3Fs%3D32&amp;r=G"
                            alt="" />&nbsp;<span style="font-weight:bold;" class="best_in_place" data-url='<?php echo $update_path; ?>' data-object='json' data-attribute='reviewer_name'><?php echo $review->reviewer_name; ?></span>
                            <br />
                            <a href="<?php echo $review->reviewer_url; ?>"><?php echo $review->reviewer_url; ?></a><br />
                            <a href="mailto:<?php echo $review->reviewer_email; ?>"><?php echo $review->reviewer_email; ?></a><br />
                            <a href="?page=wpcr_view_reviews&amp;s=<?php echo $review->reviewer_ip; ?>"><?php echo $review->reviewer_ip; ?></a><br />
                            <div style="margin-left:-4px;">
                                <div style="height:22px;" class="best_in_place" 
                                     data-collection='[[1,"Rated 1 Star"],[2,"Rated 2 Stars"],[3,"Rated 3 Stars"],[4,"Rated 4 Stars"],[5,"Rated 5 Stars"]]' 
                                     data-url='<?php echo $update_path; ?>' 
                                     data-object='json'
                                     data-attribute='review_rating' 
                                     data-callback='make_stars_from_rating'
                                     data-type='select'>
                                    <?php echo $this->output_rating($review->review_rating,false); ?>
                                </div>
                            </div>
                        </td>
                        <td class="comment column-comment">
                          <div class="wpcr-submitted-on">
                            <span class="best_in_place" data-url='<?php echo $update_path; ?>' data-object='json' data-attribute='date_time'>
                            <?php echo date("m/d/Y g:i a",strtotime($review->date_time)); ?></a>&nbsp;&nbsp;
                            </span>
                            <?php if ($review->status == 1) : ?>[<a target="_blank" href="<?php echo trailingslashit(get_permalink($this->options['selected_pageid'])); ?>?wpcrp=<?php echo $this->page; ?>#hreview-<?php echo $rid;?>">View Review on Page</a>]<?php endif; ?>
                          </div>
                          <p>
                              <span style="font-size:14px; font-weight:bold;" 
                                    class="best_in_place" 
                                    data-url='<?php echo $update_path; ?>' 
                                    data-object='json'
                                    data-attribute='review_title'><?php echo $review->review_title; ?></span><br /><br />
                              <div class="best_in_place" 
                                    data-url='<?php echo $update_path; ?>' 
                                    data-object='json'
                                    data-attribute='review_text' 
									data-callback='callback_review_text'
                                    data-type='textarea'><?php echo $review_text; ?></div>
                          </p>
                          <div class="row-actions">
                            <span class="approve <?php if ($review->status == 0 || $review->status == 2) { echo 'wpcr_show'; } else { echo 'wpcr_hide'; }?>"><a title="Mark as Approved"
                            href="?page=wpcr_view_reviews&amp;action=approvereview&amp;r=<?php echo $rid;?>&amp;review_status=<?php echo $this->p->review_status;?>">
                            Mark as Approved</a>&nbsp;|&nbsp;</span>
                            <span class="unapprove <?php if ($review->status == 1 || $review->status == 2) { echo 'wpcr_show'; } else { echo 'wpcr_hide'; }?>"><a title="Mark as Unapproved"
                            href="?page=wpcr_view_reviews&amp;action=unapprovereview&amp;r=<?php echo $rid;?>&amp;review_status=<?php echo $this->p->review_status;?>">
                            Mark as Unapproved</a><?php if ($review->status != 2): ?>&nbsp;|&nbsp;<?php endif; ?></span>
                            <span class="trash <?php if ($review->status == 2) { echo 'wpcr_hide'; } else { echo 'wpcr_show'; }?>"><a title="Move to Trash" 
                            href= "?page=wpcr_view_reviews&amp;action=trashreview&amp;r=<?php echo $rid;?>&amp;review_status=<?php echo $this->p->review_status;?>">
                            Move to Trash</a><?php if ($review->status != 2): ?>&nbsp;|&nbsp;<?php endif; ?></span>
                            <span class="trash <?php if ($review->status == 2) { echo 'wpcr_hide'; } else { echo 'wpcr_show'; }?>"><a title="Delete Forever" 
                            href= "?page=wpcr_view_reviews&amp;action=deletereview&amp;r=<?php echo $rid;?>&amp;review_status=<?php echo $this->p->review_status;?>">
                            Delete Forever</a></span>
                          </div>
                        </td>
                      </tr>
                  <?php
                  }
                  ?>
                </tbody>
              </table>

              <div class="tablenav">
                <div class="alignleft actions" style="float:left;">
                      <select name="action2">
                            <option selected="selected" value="-1">Bulk Actions</option>
                            <option value="bunapprove">Unapprove</option>
                            <option value="bapprove">Approve</option>
                            <option value="btrash">Move to Trash</option>
                            <option value="bdelete">Delete Forever</option>
                      </select>&nbsp;
                      <input type="submit" class="button-secondary apply" name="act2" value="Apply" id="doaction2" />
                </div>
                <div class="alignleft actions" style="float:left;padding-left:20px;"><?php echo $this->pagination($total_reviews); ?></div>  
                <br class="clear" />
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
                
        if (isset($this->p->optin)) {        
            
            if ($this->p->Submit == 'OK!') {
                $updated_options['act_email'] = $this->p->email;
                $this->notify_activate($updated_options['act_email'],1);
            } else {
                $this->notify_activate('',1);
            }
            
            $updated_options['activate'] = 1;
            $msg = 'Thank you. Please configure the plugin below.';
        }
        else
        {	
            // reset these to 0 so we can grab the settings below
            $updated_options['ask_fields']['fname'] = 0;
            $updated_options['ask_fields']['femail'] = 0;
            $updated_options['ask_fields']['fwebsite'] = 0;
            $updated_options['ask_fields']['ftitle'] = 0;
            $updated_options['require_fields']['fname'] = 0;
            $updated_options['require_fields']['femail'] = 0;
            $updated_options['require_fields']['fwebsite'] = 0;
            $updated_options['require_fields']['ftitle'] = 0;
            $updated_options['show_fields']['fname'] = 0;
            $updated_options['show_fields']['femail'] = 0;
            $updated_options['show_fields']['fwebsite'] = 0;
            $updated_options['show_fields']['ftitle'] = 0;
		
            // quick update of all options needed
            foreach ($this->p as $col => $val) {
                if (isset($this->options[$col])) {
                    if (is_array($val)) { foreach ($val as $v) { $updated_options[$col]["$v"] = 1; } } // checkbox array
                    else { $updated_options[$col] = $val; }
                }
            }
            
            // some int validation
            $updated_options['reviews_per_page'] = intval($this->p->reviews_per_page);
            $updated_options['selected_pageid'] = intval($this->p->page_dropdown);
            $updated_options['show_aggregate_on'] = intval($this->p->show_aggregate_on);
            $updated_options['show_hcard_on'] = intval($this->p->show_hcard_on);
            $updated_options['support_us'] = intval($this->p->support_us);
            
            if ($updated_options['reviews_per_page'] < 1) { $updated_options['reviews_per_page'] = 10; }
			
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
        $this->force_update_cache(); // update any caches

        return $msg;
    }
    
    function show_activation() {
        echo '
        <div class="postbox" style="width:700px;">
            <h3>Notify me of new releases</h3>
            <div style="padding:10px; background:#ffffff;">
                <p style="color:#060;">If you would like to be notified of any critical security updates, please enter your email address below. Your information will only be used for notification of future releases.</p><br />
                <form method="post" action="">
                    <input type="hidden" name="optin" value="1" />
                    <label for="email">Email Address: </label><input type="text" size="32" id="email" name="email" />&nbsp;
                    <input type="submit" class="button-primary" value="OK!" name="Submit" />&nbsp;
                    <input type="submit" class="button-primary" value="No Thanks!" name="Submit" />
                </form>
                <p style="color:#b00;">Please click "OK!" or "No Thanks!" above to access the plugin settings.</p>
            </div>			
        </div>';
    }
	
    function my_get_pages() { /* gets pages, even if hidden using a plugin */
        global $wpdb;
        
        $res = $wpdb->get_results("select ID, post_title from ". $wpdb->posts ." where post_status = 'publish' and post_type = 'page' order by ID");
        return $res;
    }
    
    function rand_string( $length ) {
	$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";	

	$size = strlen( $chars );
	for( $i = 0; $i < $length; $i++ ) {
            $str .= $chars[ rand( 0, $size - 1 ) ];
	}

	return $str;
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
            $su_checked = 'checked';
        }
		
        $af = array('fname' => '','femail' => '','fwebsite' => '','ftitle' => '');
        if ($this->options['ask_fields']['fname'] == 1) { $af['fname'] = 'checked'; }
        if ($this->options['ask_fields']['femail'] == 1) { $af['femail'] = 'checked'; }
        if ($this->options['ask_fields']['fwebsite'] == 1) { $af['fwebsite'] = 'checked'; }
        if ($this->options['ask_fields']['ftitle'] == 1) { $af['ftitle'] = 'checked'; }

        $rf = array('fname' => '','femail' => '','fwebsite' => '','ftitle' => '');
        if ($this->options['require_fields']['fname'] == 1) { $rf['fname'] = 'checked'; }
        if ($this->options['require_fields']['femail'] == 1) { $rf['femail'] = 'checked'; }
        if ($this->options['require_fields']['fwebsite'] == 1) { $rf['fwebsite'] = 'checked'; }
        if ($this->options['require_fields']['ftitle'] == 1) { $rf['ftitle'] = 'checked'; }
        
        $sf = array('fname' => '','femail' => '','fwebsite' => '','ftitle' => '');
        if ($this->options['show_fields']['fname'] == 1) { $sf['fname'] = 'checked'; }
        if ($this->options['show_fields']['femail'] == 1) { $sf['femail'] = 'checked'; }
        if ($this->options['show_fields']['fwebsite'] == 1) { $sf['fwebsite'] = 'checked'; }
        if ($this->options['show_fields']['ftitle'] == 1) { $sf['ftitle'] = 'checked'; }
        
        echo '
        <div class="postbox" style="width:700px;">
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
                        <small>This will enable (hidden) the hCard microformat, which includes your business contact information. This is recommended to enable for all posts &amp; pages.</small>
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
                        <div class="submit" style="padding:10px 0px 0px 0px;"><input type="submit" class="button-primary" value="Save Changes" name="Submit"></div>
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
                        <label for="reviews_per_page">Reviews shown per page: </label><input style="width:40px;" type="text" id="reviews_per_page" name="reviews_per_page" value="'.$this->options['reviews_per_page'].'" />
                        <br /><br />
                        <label for="show_aggregate_on">Show aggregate reviews on which pages: </label>
                        <select id="show_aggregate_on" name="show_aggregate_on">
                                <option ';if ($this->options['show_aggregate_on'] == 1) { echo "selected"; } echo ' value="1">Homepage &amp; review page</option>
                                <option ';if ($this->options['show_aggregate_on'] == 2) { echo "selected"; } echo ' value="2">Only the review page</option>
                        </select><br />
                        <small>This enables the aggregate (rollup) format of all combined reviews. It is recommended to use this on both the Homepage and your review page.</small>
                        <br /><br />
                        <label>Fields to ask for on review form: </label>
                        <input id="ask_fname" name="ask_fields[]" type="checkbox" '.$af['fname'].' value="fname" />&nbsp;<label for="ask_fname"><small>Name</small></label>&nbsp;&nbsp;&nbsp;
                        <input id="ask_femail" name="ask_fields[]" type="checkbox" '.$af['femail'].' value="femail" />&nbsp;<label for="ask_femail"><small>Email</small></label>&nbsp;&nbsp;&nbsp;
                        <input id="ask_fwebsite" name="ask_fields[]" type="checkbox" '.$af['fwebsite'].' value="fwebsite" />&nbsp;<label for="ask_fwebsite"><small>Website</small></label>&nbsp;&nbsp;&nbsp;
                        <input id="ask_ftitle" name="ask_fields[]" type="checkbox" '.$af['ftitle'].' value="ftitle" />&nbsp;<label for="ask_ftitle"><small>Review Title</small></label>
                        <br /><br />
                        <label>Fields to require on review form: </label>
                        <input id="require_fname" name="require_fields[]" type="checkbox" '.$rf['fname'].' value="fname" />&nbsp;<label for="require_fname"><small>Name</small></label>&nbsp;&nbsp;&nbsp;
                        <input id="require_femail" name="require_fields[]" type="checkbox" '.$rf['femail'].' value="femail" />&nbsp;<label for="require_femail"><small>Email</small></label>&nbsp;&nbsp;&nbsp;
                        <input id="require_fwebsite" name="require_fields[]" type="checkbox" '.$rf['fwebsite'].' value="fwebsite" />&nbsp;<label for="require_fwebsite"><small>Website</small></label>&nbsp;&nbsp;&nbsp;
                        <input id="require_ftitle" name="require_fields[]" type="checkbox" '.$rf['ftitle'].' value="ftitle" />&nbsp;<label for="require_ftitle"><small>Review Title</small></label>
                        <br /><br />
                        <label>Fields to show on each approved review: </label>
                        <input id="show_fname" name="show_fields[]" type="checkbox" '.$sf['fname'].' value="fname" />&nbsp;<label for="show_fname"><small>Name</small></label>&nbsp;&nbsp;&nbsp;
                        <input id="show_femail" name="show_fields[]" type="checkbox" '.$sf['femail'].' value="femail" />&nbsp;<label for="show_femail"><small>Email</small></label>&nbsp;&nbsp;&nbsp;
                        <input id="show_fwebsite" name="show_fields[]" type="checkbox" '.$sf['fwebsite'].' value="fwebsite" />&nbsp;<label for="show_fwebsite"><small>Website</small></label>&nbsp;&nbsp;&nbsp;
                        <input id="show_ftitle" name="show_fields[]" type="checkbox" '.$sf['ftitle'].' value="ftitle" />&nbsp;<label for="show_ftitle"><small>Review Title</small></label>
                        <br />
                        <small>It is usually NOT a good idea to show email addresses publicly.</small>
                        <br /><br />
                        <label for="title_tag">Heading to use for Review Titles: </label>
                        <select id="title_tag" name="title_tag">
                            <option ';if ($this->options['title_tag'] == 'h2') { echo "selected"; } echo ' value="h2">H2</option>
                            <option ';if ($this->options['title_tag'] == 'h3') { echo "selected"; } echo ' value="h3">H3</option>
                            <option ';if ($this->options['title_tag'] == 'h4') { echo "selected"; } echo ' value="h4">H4</option>
                            <option ';if ($this->options['title_tag'] == 'h5') { echo "selected"; } echo ' value="h5">H6</option>
                            <option ';if ($this->options['title_tag'] == 'h6') { echo "selected"; } echo ' value="h6">H7</option>
                        </select>
                        <br /><br />
                        <label for="goto_leave_text">Button text used to show review form: </label><input style="width:250px;" type="text" id="goto_leave_text" name="goto_leave_text" value="'.$this->options['goto_leave_text'].'" />
                        <br />
                        <small>This button will be shown above the first review.</small>
                        <br /><br />
                        <label for="leave_text">Text to be displayed above review form: </label><input style="width:250px;" type="text" id="leave_text" name="leave_text" value="'.$this->options['leave_text'].'" />
                        <br />
                        <small>This will be shown as a heading immediately above the review form.</small>
                        <br /><br />
                        <label for="submit_button_text">Text to use for review form submit button: </label><input style="width:200px;" type="text" id="submit_button_text" name="submit_button_text" value="'.$this->options['submit_button_text'].'" />
                        <br /><br />
                        <input id="support_us" name="support_us" type="checkbox" '.$su_checked.' value="1" />&nbsp;<label for="support_us"><small>Support our work and keep this plugin free. By checking this box, a small "Powered by WP Customer Reviews" link will be placed at the bottom of your reviews page.</small></label>
                        <br />
                        <div class="submit" style="padding:10px 0px 0px 0px;"><input type="submit" class="button-primary" value="Save Changes" name="Submit"></div>
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
        		
        if ($this->p->Submit == 'Save Changes')
        {
            $msg = $this->update_options();
            $this->get_options();
        }
        
        if (isset($this->p->email)) {
            $msg = $this->update_options();
            $this->get_options();
        }
        
        echo '
        <div id="wpcr_respond_1" class="wrap">
            <h2>WP Customer Reviews - Options</h2>';
            if ($msg) { echo '<h3 style="color:#a00;">'.$msg.'</h3>'; }
            echo '
            <div class="metabox-holder">
            <div class="postbox" style="width:700px;">
                <h3 style="cursor:default;">About WP Customer Reviews</h3>
                <div style="padding:0 10px; background:#ffffff;">
                    <p>
                        Version: <strong>'.$this->plugin_version.'</strong><br /><br />
                        WP Customer Reviews allows your customers and visitors to leave reviews or testimonials of your services. Reviews are Microformat enabled and can help crawlers such as Google Local Search and Google Places to index these reviews. The plugin also allows for your business information, in hCard microformat, to be (non-visibly) added to all pages.
                    </p>
                </div>
                <div style="padding:6px; background:#eaf2fa;">
                    Plugin Homepage: <a target="_blank" href="http://www.gowebsolutions.com/plugins/wp-customer-reviews/">http://www.gowebsolutions.com/plugins/wp-customer-reviews/</a><br /><br />
                    Support Forum: <a target="_blank" href="http://wordpress.org/tags/wp-customer-reviews?forum_id=10">http://wordpress.org/tags/wp-customer-reviews?forum_id=10</a><br /><br />
                    Support Email: <a href="mailto:aaron@gowebsolutions.com">aaron@gowebsolutions.com</a><br /><br />
                    <div style="color:#060;font-weight:bold;text-align:center;">If you like this plugin, please <a target="_blank" href="http://wordpress.org/extend/plugins/wp-customer-reviews/">login and rate it 5 stars here</a>.</div>
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
    
    function get_aggregate_reviews() {
        if ($this->got_aggregate !== false) { return $this->got_aggregate; }
        
        global $wpdb;
        
        $row = $wpdb->get_results( "SELECT COUNT(*) AS total,AVG(review_rating) AS aggregate_rating,MAX(review_rating) AS max_rating FROM `$this->dbtable` WHERE status=1" );
                
        /* make sure we have at least one review before continuing below */
        if ($wpdb->num_rows == 0 || $row[0]->total == 0) {
            $this->got_aggregate = array("aggregate" => 0,"max" => 0,"total" => 0,"text" => 'Reviews for my site');
            return false;
        }
            
        $aggregate_rating = $row[0]->aggregate_rating;
        $max_rating = $row[0]->max_rating;
        $total_reviews = $row[0]->total;
        
        $row = $wpdb->get_results( "SELECT review_text FROM `$this->dbtable` WHERE status=1 ORDER BY id DESC LIMIT 1 " );
	$sample_text = substr($row[0]->review_text,0,180);
        
        $this->got_aggregate = array("aggregate" => $aggregate_rating,"max" => $max_rating,"total" => $total_reviews,"text" => $sample_text);
        return true;
    }
    
    function get_reviews($startpage,$perpage,$status) {        
        global $wpdb;
        
        $startpage = $startpage - 1; /* mysql starts at 0 instead of 1, so reduce them all by 1 */
        if ($startpage < 0) { $startpage = 0; }
		
        $limit = 'LIMIT '.$startpage*$perpage.','.$perpage;
        
        if ($status == -1) { $qry_status = '1=1'; } else { $qry_status = "status=$status"; }
        
        $reviews = $wpdb->get_results( "SELECT 
            id,
            date_time,
            reviewer_name,
            reviewer_email,
            review_title,
            review_text,
            review_rating,
            reviewer_url,
            status
            FROM `$this->dbtable` WHERE $qry_status ORDER BY id DESC $limit
            " );
			
        $total_reviews = $wpdb->get_results( "SELECT COUNT(*) AS total FROM `$this->dbtable` WHERE $qry_status" );
        $total_reviews = $total_reviews[0]->total;
		
        return array($reviews,$total_reviews);
    }
    
    function aggregate_footer() {
        
        $output2 = '<div style="clear:both;margin:0;padding:0;">&nbsp;</div>';
        $output2 .= $this->output_aggregate();
		
        if ($this->options['show_hcard_on'] != 0) {
					
            // start - make sure we should continue
            global $post;
            $show = false;
            $is_active_page = $this->options['selected_pageid'] == $post->ID;
            if ($this->options['show_hcard_on'] == 1) { $show = true; }
            else if ($this->options['show_hcard_on'] == 2 && ( is_home() || is_front_page() ) ) { $show = true; }
            else if ($this->options['show_hcard_on'] == 3 && $is_active_page ) { $show = true; }
            // end - make sure we should continue

            if ($show) {
                    $output2 .= '
                    <div id="wpcr-hcard" class="vcard" style="display:none;">
                         <a class="url fn org" href="'.$this->options['business_url'].'">'.$this->options['business_name'].'</a>
                         <a class="email" href="mailto:'.$this->options['business_email'].'">'.$this->options['business_email'].'</a>
                         <span class="adr">
                              <span class="street-address">'.$this->options['business_street'].'</span>
							  <span class="locality">'.$this->options['business_city'].'</span>,
                              <span class="region">'.$this->options['business_state'].'</span>,
							  <span class="postal-code">'.$this->options['business_zip'].'</span>
                              <span class="country-name">'.$this->options['business_country'].'</span>
                         </span>
						 <span class="tel">'.$this->options['business_phone'].'</span>
                    </div>
                    ';
            }

			$output2 = preg_replace('/\n\r|\r\n|\n|\r|\t|\s{2}/', '', $output2); /* minify */
        }
        
        return $output2;
    }
    
    function output_aggregate() {
        global $post;

        // start - make sure we should continue
        global $post;
        $is_active_page = $this->options['selected_pageid'] == $post->ID;		
        if ($this->options['show_aggregate_on'] == 2 && !$is_active_page) { return ''; } // return nothing if not on review page
        if ($this->options['show_aggregate_on'] == 1 ) { // homepage and chosen review page
                if ( !is_home() && !is_front_page() && !$is_active_page ) { return ''; } // not on homepage, not on review page
        }
        if ($this->shown_aggregate) { return ''; } // dont show if already shown once
        // end - make sure we should continue

        if ( is_home() || is_front_page() ) { $homepage = 1; } else { $homepage = 0; }
		
        $this->get_aggregate_reviews(); /* get aggregate reviews into $this->got_aggregate */
        
        $summary = $this->got_aggregate["text"];       
        $best_score = number_format($this->got_aggregate["max"],1);
        $average_score = number_format($this->got_aggregate["aggregate"],1);
        
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
               <span class="votes">'.$this->got_aggregate["total"].'</span>
               <span class="count">'.$this->got_aggregate["total"].'</span>
               <span class="summary">'.$summary.'</span>
           </div>
        </div>
        ';
        
        $this->shown_aggregate = true;
        
        return $content;
    }
    
    function iso8601($time=false) {
        if ($time === false) $time = time();
        $date = date('Y-m-d\TH:i:sO', $time);
        return (substr($date, 0, strlen($date)-2).':'.substr($date, -2));
    }
	
    function pagination($total_results = 0, $range = 2) {

         $out = '';

         $showitems = ($range * 2) + 1;

         $paged = $this->page;
         if($paged == 0) { $paged = 1; }
         
         $pages = ceil($total_results / $this->options['reviews_per_page']);

         if($pages > 1)
         {
             $url = '?';
             if (is_admin()) { $url .= 'page=wpcr_view_reviews&amp;review_status='.$this->p->review_status.'&amp;'; }
             
             $out .= "<div id='wpcr_pagination'><div id='wpcr_pagination_page'>Page: </div>";

             if($paged > 2 && $paged > $range + 1 && $showitems < $pages)
             {
                if (is_admin()) {
                    $url2 = '?page=wpcr_view_reviews&amp;review_status='.$this->p->review_status.'&amp;';
                } else {
                    $url2 = get_permalink($post->ID);
                }
                $out .= "<a href='{$url2}'>&laquo;</a>"; 
             }
             
             if($paged > 1 && $showitems < $pages) { $out .= "<a href='{$url}wpcrp=".($paged - 1)."'>&lsaquo;</a>"; }

             for ($i=1; $i <= $pages; $i++)
             {
                if ($i == $paged)
                {
                    $out .= "<span class='wpcr_current'>$paged</span>";
                }
                else if ( !($i >= $paged + $range + 1 || $i <= $paged - $range - 1) || $pages <= $showitems )
                {
                    if ($i == 1) {
                        if (is_admin()) {
                            $url2 = '?page=wpcr_view_reviews&amp;review_status='.$this->p->review_status.'&amp;';
                        } else {
                            $url2 = get_permalink($post->ID);
                        }
                        $out .= "<a href='{$url2}' class='wpcr_inactive' >".$i."</a>";
                    } else {
                        $out .= "<a href='{$url}wpcrp=$i' class='wpcr_inactive' >".$i."</a>";
                    }
                }
             }

             if ($paged < $pages && $showitems < $pages) { $out .= "<a href='{$url}wpcrp=".($paged + 1)."'>&rsaquo;</a>"; }
             if ($paged < $pages-1 &&  $paged+$range-1 < $pages && $showitems < $pages) { $out .= "<a href='{$url}wpcrp=$pages'>&raquo;</a>"; }
             $out .= "</div>\n";

             return $out;
         }
    }

    function show_reviews() {
        global $post;
        
        if ($this->options['selected_pageid'] != $post->ID) { return $the_content_original; }
		
        $the_content = '<div style="clear:both;margin:0;padding:0;">&nbsp;</div>'; // our content
        
        $status_msg = '';
		$status_css = '';
        if ( isset( $_COOKIE['wpcr_status_msg'] ) ) {
            $status_msg = $_COOKIE['wpcr_status_msg'];
            $status_msg .= "\n<script type='text/javascript'>\n<!--\nwpcr_del_cookie('wpcr_status_msg');\n//-->\n</script>\n";
			$status_css = 'padding-bottom:15px;';
        }
                
        $the_content .= '<div id="wpcr_respond_1"><div style="'.$status_css.'" class="wpcr_status_msg">'.$status_msg.'</div>'; /* show errors or thank you message here */
        $the_content .= '<p><a id="wpcr_button_1" href="javascript:void(0);">'.$this->options['goto_leave_text'].'</a></p><hr />';
		
        $arr_Reviews = $this->get_reviews($this->page,$this->options['reviews_per_page'],1);
        
        $reviews = $arr_Reviews[0];
        $total_reviews = intval($arr_Reviews[1]);
		
        $reviews_content = '';
        $ftitle = '';
        $hidesummary = '';
        $title_tag = $this->options['title_tag'];
	
        /* trying to access a page that does not exists -- send to main page */
        if (isset($this->p->wpcrp) && count($reviews) == 0) {
            $url = get_permalink($post->ID);
            $the_content = $this->js_redirect($url);
            return $the_content_original.$the_content;
        }
        
        if (count($reviews) == 0) {
            $the_content .= '<p>There are no reviews yet. Be the first to leave yours!</p>';
        } else {            
            foreach ($reviews as $review)
            {
                $review->review_text .= '<br />';

                $hide_name = '';
                if ($this->options['show_fields']['fname'] == 0) {
                    $review->reviewer_name = 'Anonymous';
                    $hide_name = 'wpcr_hide';
                }
                if ($review->reviewer_name == '') { $review->reviewer_name = 'Anonymous'; }
                
                if ($this->options['show_fields']['fwebsite'] == 1 && $review->reviewer_url != '') { 
                        $review->review_text .= '<br /><small><a href="'.$review->reviewer_url.'">'.$review->reviewer_url.'</a></small>';
                }
                if ($this->options['show_fields']['femail'] == 1 && $review->reviewer_email != '') { 
                        $review->review_text .= '<br /><small>'.$review->reviewer_email.'</small>';
                }
                if ($this->options['show_fields']['ftitle'] == 1) { 
                        // do nothing
                } else {
                        $review->review_title = substr($review->review_text,0,150);
                        $hidesummary = 'wpcr_hide';
                }

                $review->review_text = nl2br($review->review_text);
			
                $reviews_content .= '
                    <div class="hreview" id="hreview-'.$review->id.'">
                        <'.$title_tag.' class="summary '.$hidesummary.'">'.$review->review_title.'</'.$title_tag.'>
                        <div class="wpcr_fl wpcr_sc">
                            <abbr class="rating" title="'.$review->review_rating.'"></abbr>
                            <div class="wpcr_rating">
                                '.$this->output_rating($review->review_rating,false).'
                            </div>					
                        </div>
                        <div class="wpcr_fl wpcr_rname">
                            <abbr title="'.$this->iso8601(strtotime($review->date_time)).'" class="dtreviewed">'.date("M d, Y",strtotime($review->date_time)).'</abbr>&nbsp;<span class="'.$hide_name.'">by</span>&nbsp;<span class="reviewer vcard" id="hreview-wpcr-reviewer-'.$review->id.'"><span class="fn '.$hide_name.'">'.$review->reviewer_name.'</span></span>
                        </div>
                        <div class="wpcr_clear wpcr_spacing1"></div>
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
                        <blockquote class="description"><p>'.$review->review_text.'</p></blockquote>
                        <span style="display:none;" class="version">0.3</span>
                   </div>
                   <hr />';
            }
        }
        
        $the_content .= $this->output_aggregate();
        $the_content .= $this->show_reviews_form($status_msg);
        $the_content .= $reviews_content;
        $the_content .= $this->pagination($total_reviews);
        if ($this->options['support_us'] == 1) {
            $the_content .= '<div class="wpcr_clear wpcr_power">Powered by <strong><a href="http://www.gowebsolutions.com/plugins/wp-customer-reviews/">WP Customer Reviews</a></strong></div>';
        }
        $the_content .= '</div>';
        
        echo $the_content;
    }
	
    function output_rating($rating,$enable_hover) {
        $out = '';

        $rating_width = 20 * $rating; /* 20% for each star if having 5 stars */

        $out .= '<div class="sp_rating">';

        if ($enable_hover) {
            $out .= '
            <div class="status">
                <div class="score">
                    <a class="score1">1</a>
                    <a class="score2">2</a>
                    <a class="score3">3</a>
                    <a class="score4">4</a>
                    <a class="score5">5</a>
                </div>
            </div>
            ';
        }

        $out .= '<div class="base"><div class="average" style="width:'.$rating_width.'%"></div></div>';
        $out .= '</div>';

        return $out;
    }
    
    function show_reviews_form() { 
        global $post, $current_user;
               
        $fields = '';
        
        /* a silly yet crazy and possibly effective antispam measure.. bots won't have a clue */
        $rand_prefixes = array();
        for ($i=0; $i<15; $i++) {
            $rand_prefixes[] = $this->rand_string(mt_rand(1,8));
        }
        
        if ($this->options['ask_fields']['fname'] == 1) {
            if ($this->options['require_fields']['fname'] == 1) { $req = '*'; } else { $req = ''; }
            $fields .= '<tr><td><label for="'.$rand_prefixes[0].'-fname" class="comment-field">Name: '.$req.'</label></td><td><input class="text-input" type="text" id="'.$rand_prefixes[0].'-fname" name="'.$rand_prefixes[0].'-fname" value="'.$this->p->fname.'" /></td></tr>';
        }
        if ($this->options['ask_fields']['femail'] == 1) {
            if ($this->options['require_fields']['femail'] == 1) { $req = '*'; } else { $req = ''; }
            $fields .= '<tr><td><label for="'.$rand_prefixes[1].'-femail" class="comment-field">Email: '.$req.'</label></td><td><input class="text-input" type="text" id="'.$rand_prefixes[1].'-femail" name="'.$rand_prefixes[1].'-femail" value="'.$this->p->femail.'" /></td></tr>';
        }
        if ($this->options['ask_fields']['fwebsite'] == 1) { 
            if ($this->options['require_fields']['fwebsite'] == 1) { $req = '*'; } else { $req = ''; }
            $fields .= '<tr><td><label for="'.$rand_prefixes[2].'-fwebsite" class="comment-field">Website: '.$req.'</label></td><td><input class="text-input" type="text" id="'.$rand_prefixes[2].'-fwebsite" name="'.$rand_prefixes[2].'-fwebsite" value="'.$this->p->fwebsite.'" /></td></tr>';
        }
        if ($this->options['ask_fields']['ftitle'] == 1) { 
            if ($this->options['require_fields']['ftitle'] == 1) { $req = '*'; } else { $req = ''; }
            $fields .= '<tr><td><label for="'.$rand_prefixes[3].'-ftitle" class="comment-field">Review Title: '.$req.'</label></td><td><input class="text-input" type="text" id="'.$rand_prefixes[3].'-ftitle" name="'.$rand_prefixes[3].'-ftitle" maxlength="150" value="'.$this->p->ftitle.'" /></td></tr>';
        }
        
        $some_required = '';
        $req_js = "<script type='text/javascript'>\n<!--\n";
        foreach ($this->options['require_fields'] as $col => $val) {
            if ($val == 1) {
                $req_js .= "wpcr_req.push('$col');";
                $some_required = '<small>* Required Field</small>';
            }
        }
        $req_js .= "\n//-->\n</script>\n";    

        /* different output variables make it easier to debug this section */
        $out = '<div id="wpcr_respond_2">'.$req_js.'
                    <form class="wpcrcform" id="wpcr_commentform" method="post" action="javascript:void(0);">
                        <div id="wpcr_div_2">
                            <input type="hidden" id="frating" name="frating" />
                            <table id="wpcr_table_2">
                                <tbody>
                                    <tr><td colspan="2"><div id="wpcr_postcomment">'.$this->options["leave_text"].'</div></td></tr>
                                    '.$fields;

        $out2 = '   
                                    <tr>
                                        <td><label class="comment-field">Rating:</label></td>
                                        <td><div class="wpcr_rating">'.$this->output_rating(0,true).'</div></td>
                                    </tr>';

        $out3 = '
                                    <tr><td colspan="2"><label for="'.$rand_prefixes[5].'-ftext" class="comment-field">Review:</label></td></tr>
                                    <tr><td colspan="2"><textarea id="'.$rand_prefixes[5].'-ftext" name="'.$rand_prefixes[5].'-ftext" rows="8" cols="50">'.$this->p->ftext.'</textarea></td></tr>
                                    <tr>
                                        <td colspan="2" id="wpcr_check_confirm">
                                            '.$some_required.'
                                            <div class="wpcr_clear"></div>    
                                            <input type="checkbox" name="'.$rand_prefixes[6].'-fconfirm1" id="fconfirm1" value="1" />
                                            <div class="wpcr_fl"><input type="checkbox" name="'.$rand_prefixes[7].'-fconfirm2" id="fconfirm2" value="1" /></div><div class="wpcr_fl" style="margin:-2px 0px 0px 5px"><label for="fconfirm2">Check this box to confirm you are human.</label></div>
                                            <div class="wpcr_clear"></div>
                                            <input type="checkbox" name="'.$rand_prefixes[8].'-fconfirm3" id="fconfirm3" value="1" />
                                        </td>
                                    </tr>
                                    <tr><td colspan="2"><input id="wpcr_submit_btn" name="submitwpcr_'.$post->ID.'" type="submit" value="'.$this->options['submit_button_text'].'" /></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </form>';

        $out4 = '<hr />
                </div>
                <div class="wpcr_clear wpcr_pb5"></div>';
            
        return $out.$out2.$out3.$out4;
    }

    function add_review() {
        global $wpdb;
        
        /* begin - some antispam magic */
        $this->newp = new stdClass();
        
        foreach ($this->p as $col => $val) {
            $pos = strpos($col,'-');
            if ($pos !== false) {
                $col = substr($col,$pos + 1); /* off by one */
            }
            $this->newp->$col = $val;
        }
        
        $this->p = $this->newp;
        unset($this->newp);
        /* end - some antispam magic */
                
        // some sanitation
        $date_time = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'];        
        $this->p->fname = trim(strip_tags($this->p->fname));
        $this->p->femail = trim(strip_tags($this->p->femail));
        $this->p->ftitle = trim(strip_tags($this->p->ftitle));
        $this->p->ftext = trim(strip_tags($this->p->ftext));
        $this->p->frating = intval($this->p->frating);
        
        /* begin - server-side validation */
        $errors = '';
        
        foreach ($this->options['require_fields'] as $col => $val) {
            if ($val == 1) {
                if ($this->p->$col == '') {
                    $nice_name = ucfirst(substr($col,1));
                    $errors .= 'You must include your '.$nice_name.'.<br />';
                }
            }
        }
        
        if ($this->p->femail != '' && !preg_match('/^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/', $this->p->femail)) {
            $errors .= 'The email address provided is not valid.<br />';
        }
        
        if ($this->p->fwebsite != '' && !preg_match('/^\S+:\/\/\S+\.\S+.+$/', $this->p->fwebsite)) {
            $errors .= 'The website provided is not valid. Be sure to include http://<br />';
        }
        
        if (intval($this->p->fconfirm1) == 1 || intval($this->p->fconfirm3) == 1) {
            $errors .= 'You have triggered our anti-spam system. Please try again. Code 001.<br />';
        }
        
        if (intval($this->p->fconfirm2) != 1) {
            $errors .= 'You have triggered our anti-spam system. Please try again. Code 002<br />';
        }
		
        if ($this->p->frating < 1 || $this->p->frating > 5) {
            $errors .= 'You have triggered our anti-spam system. Please try again. Code 003<br />';
        }

        if (strlen(trim($this->p->ftext)) < 30) {
            $errors .= 'You must include a review. Please make reviews at least a couple of sentences.<br />';
        }
        
        /* returns true for errors */
        if ($errors) { return array(true,"<div class='wpcr_status_msg'>$errors</div>"); }
        /* end - server-side validation */
        
        $query = $wpdb->prepare("INSERT INTO `$this->dbtable` 
                (date_time, reviewer_name, reviewer_email, reviewer_ip, review_title, review_text, status, review_rating, reviewer_url) 
                VALUES (%s, %s, %s, %s, %s, %s, %d, %d, %s)",
                $date_time, $this->p->fname, $this->p->femail, $ip, $this->p->ftitle, $this->p->ftext, 0, $this->p->frating, $this->p->fwebsite);
        
        $wpdb->query($query);
        
        @wp_mail( get_bloginfo('admin_email'), "WP Customer Reviews: New Review Posted on ".date('m/d/Y h:i'), "A new review has been posted for ".$this->options['business_name']." via WP Customer Reviews. \n\nYou will need to login to the admin area and approve this review before it will appear on your site.");
        
        /* returns false for no error */
        return array(false,'<div style="color:#c00;font-weight:bold;padding-bottom:15px;padding-top:15px;">Thank you for your comments. All submissions are moderated and if approved, yours will appear soon.</div>');
    }
	
    function force_update_cache() {
        // update page we are using, this will force it to update with caching plugins
        $pageID = $this->options['selected_pageid'];		
        $post = get_post($pageID);
        
        if ($post) {           
            wp_update_post($post); // the magic            
        }
        
        if (function_exists('wp_cache_post_change')) {
            wp_cache_post_change( $pageID ); /* just in case for wp super cache */
        }
    }
    
    function deactivate() {
        $this->notify_activate($this->options['act_email'],2);
    }

    /* 
     * This is used purely for analytics and for notification of critical security releases.
     * And it gives us a chance to review who is using it and to verify theme and version compatibility
     * None of this information will ever be shared, sold, or given away.
     */ 
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
    
    function js_redirect($url,$cookie = array()) {
        $out = "
        <div style='clear:both;text-align:center;padding:10px;'>Processing... Please wait...</div>
        <script type='text/javascript'>\n<!--\n";
		  foreach ($cookie as $col => $val) {
			 $val = preg_replace("/\r?\n/", "\\n", addslashes($val));
		     $out .= "document.cookie=\"$col=$val\";\n";
		  }
		  $out .= "window.location='$url';\n";
		  $out .= "//-->\n</script>\n";
		return $out;
    }
    	
    function init() { /* used for init and admin_init */    
        $this->wpurl = get_bloginfo('wpurl');
        $this->page = intval($_GET['wpcrp']);
        if ($this->page < 1) { $this->page = 1; }
        
        $this->make_p_obj(); // make P variables object
        $this->get_options(); // populate the options array
        $this->check_migrate(); // call on every instance to see if we have upgraded in any way
    }
    
    function get_admin_path() { /* get the real wp-admin path, even if renamed */
        $admin_path = $_SERVER['REQUEST_URI'];            
        $admin_path = substr($admin_path, 0, stripos($admin_path,'plugins.php'));
        
        /* not in plugins.php, try again for admin.php */
        if ($admin_path === false || $admin_path === '') {
            $admin_path = $_SERVER['REQUEST_URI'];            
            $admin_path = substr($admin_path, 0, stripos($admin_path,'admin.php'));
        }
        
        return $admin_path;
    }
    
    function activate() {
        global $wpdb;
        
        $existing_tbl = $wpdb->get_var("SHOW TABLES LIKE '$this->dbtable'");
        if ( $existing_tbl != $this->dbtable ) {
            $this->createReviewtable();
        }
        
        add_option('wpcr_gotosettings', true); /* used for redirecting to settings page upon initial activation */
    }
    
    function admin_init() {
        $this->init();
        $this->enqueue_stuff(true);
	
        /* used for redirecting to settings page upon initial activation */
        if (get_option('wpcr_gotosettings', false)) {
            delete_option('wpcr_gotosettings');
			
            if ($this->p->action == 'activate-plugin') { return false; } /* no auto settings redirect if upgrading */
                   
            $url = $this->get_admin_path().'options-general.php?page=wpcr_options';
			
			if (headers_sent() == true) {
				$this->js_redirect($url); /* use JS redirect */
			} else {
				ob_end_clean();
				wp_redirect($url); /* nice redirect */
			}
			
			exit();
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
}

$WPCustomerReviews = new WPCustomerReviews();
register_activation_hook(__FILE__, array( &$WPCustomerReviews, 'activate' ));
register_deactivation_hook( __FILE__, array( &$WPCustomerReviews, 'deactivate' ));
?>