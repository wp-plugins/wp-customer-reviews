<?php
/* 
 * Plugin Name:   WP Customer Reviews
 * Version:       2.0.2
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
    var $plugin_version = '2.0.2';
    var $dbtable = 'wpcreviews';
    var $options = array();
    var $got_aggregate = false;
	var $shown_hcard = false;
    var $p = '';
    var $page = 1;

    function WPCustomerReviews() {
        global $table_prefix;
		
		define('IN_WPCR',1);
		
        $this->dbtable = $table_prefix.$this->dbtable;
				
		add_action('the_content', array(&$this, 'do_the_content'), 10); /* 10 prevents a conflict with some odd themes */
        add_action('init', array(&$this, 'init'));
        add_action('admin_init', array(&$this, 'admin_init'));
        add_action('get_header', array(&$this, 'enqueue_stuff')); /* need to enqueue before wp_head gets called */
		
		add_filter('plugin_action_links_'.plugin_basename(__FILE__), array(&$this,'plugin_settings_link') );
		add_action('admin_menu', array(&$this,'addmenu') );
        add_action('wp_ajax_update_field', array(&$this,'admin_view_reviews') ); /* special ajax stuff */
		add_action('save_post', array(&$this,'admin_save_post'), 10, 2); /* 2 arguments */
    }
	
	/* keep out of admin file */
	function plugin_settings_link($links) {
		$settings_link = '<a href="options-general.php?page=wpcr_options"><img src="'.$this->getpluginurl().'star.png" />&nbsp;Settings</a>'; 
        array_unshift($links, $settings_link); 
        return $links; 
	}
	
	/* keep out of admin file */
	function addmenu() {		
        add_options_page('Customer Reviews', '<img src="'.$this->getpluginurl().'star.png" />&nbsp;Customer Reviews', 'manage_options', 'wpcr_options', array(&$this, 'admin_options'));
        add_menu_page('Customer Reviews', 'Customer Reviews', 'edit_others_posts', 'wpcr_view_reviews', array(&$this, 'admin_view_reviews'), $this->getpluginurl().'star.png', 50); /* 50 should be underneath comments */
		
		global $WPCustomerReviewsAdmin;
		$this->include_admin(); /* include admin functions */
		$WPCustomerReviewsAdmin->add_meta_box();
	}
	
	/* forward to admin file */
	function admin_options() {
		global $WPCustomerReviewsAdmin;
		$this->include_admin(); /* include admin functions */
		$WPCustomerReviewsAdmin->real_admin_options();
	}
	
	/* forward to admin file */
	function admin_save_post($post_id,$post) {
		global $WPCustomerReviewsAdmin;
		$this->include_admin(); /* include admin functions */
		$WPCustomerReviewsAdmin->real_admin_save_post($post_id,$post);
	}
	
	/* forward to admin file */
	function admin_view_reviews() {
		global $WPCustomerReviewsAdmin;
		$this->include_admin(); /* include admin functions */
		$WPCustomerReviewsAdmin->real_admin_view_reviews();
	}

    function get_options() { 
        $home_domain = @parse_url(get_home_url());
        $home_domain = $home_domain['scheme']."://".$home_domain['host'].'/';
        
		/****
		!!!!!
		Add age and gender to possible options for ask/require/show
		!!!!!
		****/
		
        $default_options = array(
            'act_email' => '',
            'activate' => 0,
			'ask_custom' => array(),
            'ask_fields' => array('fname' => 1, 'femail' => 1, 'fwebsite' => 1, 'ftitle' => 1, 'fage' => 0, 'fgender' => 0),
            'business_city' => '',
            'business_country' => 'USA',
            'business_email' => get_bloginfo('admin_email'),
            'business_name' => get_bloginfo('name'),
            'business_phone' => '',
            'business_state' => '',
            'business_street' => '',
            'business_url' => $home_domain,
            'business_zip' => '',
            'dbversion' => 0,
			'field_custom' => array(),
            'goto_leave_text' => 'Click here to submit your review.',
			'hreview_type' => 'business',
            'leave_text' => 'Submit your review',
			'require_custom' => array(),
            'require_fields' => array('fname' => 1, 'femail' => 1, 'fwebsite' => 0, 'ftitle' => 0, 'fage' => 0, 'fgender' => 0),
            'reviews_per_page' => 10,
            'show_custom' => array(),
			'show_fields' => array('fname' => 1, 'femail' => 0, 'fwebsite' => 0, 'ftitle' => 1, 'fage' => 0, 'fgender' => 0),
            'show_hcard_on' => 1,
            'submit_button_text' => 'Submit your review',
            'support_us' => 1,
            'title_tag' => 'h2'
        );
        $this->options = get_option('wpcr_options',$default_options);
        
        /* magically easy migrations to newer versions */
        $has_new = false;
        foreach ($default_options as $col => $def_val) {
            
            if (!isset($this->options[$col])) {
                $this->options[$col] = $def_val;
                $has_new = true;
            }
            
            if (is_array($def_val)) {
                foreach ($def_val as $acol => $aval) {
                    if (!isset($this->options[$col][$acol]))
					{					
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
        
        /* remove me after official release */
        $this->options['dbversion'] = intval(str_replace('.','',$this->options['dbversion']));
        $plugin_db_version = intval(str_replace('.','',$this->plugin_version));
        
        if ($this->options['dbversion'] == $plugin_db_version) { return false; }
        
        /* initial installation */
        if ($this->options['dbversion'] == 0) { 
            $this->options['dbversion'] = $plugin_db_version;
            update_option('wpcr_options', $this->options);
            return false;
        }
        
        /* check for upgrades if needed */
        
        /* upgrade to 2.0.0 */
        if ($this->options['dbversion'] < 200) {
			
			/* add multiple page support to database */
			/* using one query per field to prevent errors if a field already exists */
            $wpdb->query("ALTER TABLE `$this->dbtable` ADD `page_id` INT(11) NOT NULL DEFAULT '0', ADD INDEX ( `page_id` )");
			$wpdb->query("ALTER TABLE `$this->dbtable` ADD `custom_fields` text");
			
			/* change all current reviews to use the selected page id */
			$pageID = intval( $this->options['selected_pageid'] );
			$wpdb->query("UPDATE `$this->dbtable` SET `page_id`=$pageID WHERE `page_id`=0");
			
			/* add new meta to existing selected page */
			update_post_meta($pageID, 'wpcr_enable', 1);
			
            $this->options['dbversion'] = 200;
            update_option('wpcr_options', $this->options);
            $migrated = true;
        }
        
        /* done with all migrations, push dbversion to current version */
        if ($this->options['dbversion'] != $plugin_db_version || $migrated == true) {
            
			$this->options['dbversion'] = $plugin_db_version;
            update_option('wpcr_options', $this->options);
			
			global $WPCustomerReviewsAdmin;
			$this->include_admin(); /* include admin functions */
            $WPCustomerReviewsAdmin->notify_activate($this->options['act_email'],3);
            $WPCustomerReviewsAdmin->force_update_cache(); /* update any caches */
			
            return true;
        }
        
        return false;
    }
	    
    function enqueue_stuff() {
        global $post;
        
		$is_active_page = get_post_meta($post->ID, 'wpcr_enable', true);
		if ($is_active_page)
		{ 
            wp_enqueue_script('jquery');
            wp_register_script('wp-customer-reviews',$this->getpluginurl().'wp-customer-reviews.js',array(),$this->plugin_version);
            wp_enqueue_script('wp-customer-reviews');
                  
            /* do this here so we can redirect */
			$GET_P = "submitwpcr_$post->ID";
							
			if ($this->p->$GET_P == $this->options['submit_button_text']) {
				$msg = $this->add_review($post->ID);

				$has_error = $msg[0];
				$status_msg = $msg[1];
				$cookie = array('wpcr_status_msg' => $status_msg);
				
				$url = get_permalink($post->ID);
				
				if (headers_sent() == true) {
					echo $this->js_redirect($url,$cookie); /* use JS redirect and add cookie before redirect */
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
        
        /* styles needed for hidden hcard so we just include them everywhere */
        wp_register_style('wp-customer-reviews',$this->getpluginurl().'wp-customer-reviews.css',array(),$this->plugin_version);        
        wp_enqueue_style('wp-customer-reviews');
    }
    
    function rand_string( $length ) {
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";	

		$size = strlen( $chars );
		for( $i = 0; $i < $length; $i++ ) {
				$str .= $chars[ rand( 0, $size - 1 ) ];
		}

		return $str;
    }
    
    function get_aggregate_reviews($pageID) {
        if ($this->got_aggregate !== false) { return $this->got_aggregate; }
        
        global $wpdb;
        
		$pageID = intval($pageID);
        $row = $wpdb->get_results( "SELECT COUNT(*) AS total,AVG(review_rating) AS aggregate_rating,MAX(review_rating) AS max_rating FROM `$this->dbtable` WHERE page_id=$pageID AND status=1" );
                
        /* make sure we have at least one review before continuing below */
        if ($wpdb->num_rows == 0 || $row[0]->total == 0) {
            $this->got_aggregate = array("aggregate" => 0,"max" => 0,"total" => 0,"text" => 'Reviews for my site');
            return false;
        }
            
        $aggregate_rating = $row[0]->aggregate_rating;
        $max_rating = $row[0]->max_rating;
        $total_reviews = $row[0]->total;
        
        $row = $wpdb->get_results( "SELECT review_text FROM `$this->dbtable` WHERE page_id=$pageID AND status=1 ORDER BY id DESC LIMIT 1" );
		$sample_text = substr($row[0]->review_text,0,180);
        
        $this->got_aggregate = array("aggregate" => $aggregate_rating,"max" => $max_rating,"total" => $total_reviews,"text" => $sample_text);
        return true;
    }
    
    function get_reviews($postID,$startpage,$perpage,$status) {        
        global $wpdb;
        
        $startpage = $startpage - 1; /* mysql starts at 0 instead of 1, so reduce them all by 1 */
        if ($startpage < 0) { $startpage = 0; }
		
        $limit = 'LIMIT '.$startpage*$perpage.','.$perpage;
        
        if ($status == -1) { $qry_status = '1=1'; } else { $qry_status = "status=$status"; }
		
		$postID = intval($postID);
		if ($postID == -1) { $and_post = ''; } else { $and_post = "AND `page_id`=$postID"; }
        
        $reviews = $wpdb->get_results( "SELECT 
            id,
            date_time,
            reviewer_name,
            reviewer_email,
            review_title,
            review_text,
            review_rating,
            reviewer_url,
			reviewer_ip,
            status,
			page_id,
			custom_fields
            FROM `$this->dbtable` WHERE $qry_status $and_post ORDER BY id DESC $limit
            " );
			
        $total_reviews = $wpdb->get_results( "SELECT COUNT(*) AS total FROM `$this->dbtable` WHERE $qry_status $and_post" );
        $total_reviews = $total_reviews[0]->total;
		
        return array($reviews,$total_reviews);
    }
    
    function aggregate_footer()
	{
        if ($this->options['show_hcard_on'] != 0 && $this->shown_hcard === false)
		{
			/* may need to uncomment to validate */
			/* remove_filter ('the_content', 'wpautop'); */
		
			$this->shown_hcard = true;
		
            /* start - make sure we should continue */
            global $post;
            $show = false;			
			$is_active_page = get_post_meta($post->ID, 'wpcr_enable', true);
			
            if ($this->options['show_hcard_on'] == 1) { $show = true; }
            else if ($this->options['show_hcard_on'] == 2 && ( is_home() || is_front_page() ) ) { $show = true; }
            else if ($this->options['show_hcard_on'] == 3 && $is_active_page ) { $show = true; }
            /* end - make sure we should continue */

            if ($show) { /* we append like this to prevent newlines and wpautop issues */
				$output2 .= '<div id="wpcr-hcard" class="vcard" style="display:none;">'.
					 '<a class="url fn org" href="'.$this->options['business_url'].'">'.$this->options['business_name'].'</a>'.
					 '<a class="email" href="mailto:'.$this->options['business_email'].'">'.$this->options['business_email'].'</a>'.
					 '<span class="adr">'.
						  '<span class="street-address">'.$this->options['business_street'].'</span>'.
						  '<span class="locality">'.$this->options['business_city'].'</span>,'.
						  '<span class="region">'.$this->options['business_state'].'</span>,'.
						  '<span class="postal-code">'.$this->options['business_zip'].'</span>'.
						  '<span class="country-name">'.$this->options['business_country'].'</span>'.
					 '</span>'.
					 '<span class="tel">'.$this->options['business_phone'].'</span>'.
				'</div>';
            }
        }
        
       return $output2;
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

    function do_the_content($original_content) {
        global $post;
        
		$the_content = '';
		
		$is_active_page = get_post_meta($post->ID, 'wpcr_enable', true);
		if (!$is_active_page) { 
			$the_content .= $this->aggregate_footer(); /* check if we need to show something in the footer then */
			return $original_content.$the_content;
		}
		
		/* may need to uncomment to validate */
		/* remove_filter ('the_content', 'wpautop'); */
        
        $status_msg = '';
		$status_css = '';
        if ( isset( $_COOKIE['wpcr_status_msg'] ) ) {
            $status_msg = $_COOKIE['wpcr_status_msg'];
            $status_msg .= "\n<script type='text/javascript'>\n<!--\nwpcr_del_cookie('wpcr_status_msg');\n//-->\n</script>\n";
			$status_css = 'padding-bottom:15px;';
        }
                
        $the_content .= '<div id="wpcr_respond_1"><div style="'.$status_css.'" class="wpcr_status_msg">'.$status_msg.'</div>'; /* show errors or thank you message here */
        $the_content .= '<p><a id="wpcr_button_1" href="javascript:void(0);">'.$this->options['goto_leave_text'].'</a></p><hr />';
		
        $arr_Reviews = $this->get_reviews($post->ID,$this->page,$this->options['reviews_per_page'],1);
        
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
            return $original_content.$the_content;
        }
		
		$meta_product_name = get_post_meta($post->ID, 'wpcr_product_name', true);
		if (!$meta_product_name) { $meta_product_name = get_the_title($post->ID); }
		
		$meta_product_desc = get_post_meta($post->ID, 'wpcr_product_desc', true);
		$meta_product_brand = get_post_meta($post->ID, 'wpcr_product_brand', true);
		$meta_product_upc = get_post_meta($post->ID, 'wpcr_product_upc', true);
        $meta_product_sku = get_post_meta($post->ID, 'wpcr_product_sku', true);
		$meta_product_model = get_post_meta($post->ID, 'wpcr_product_model', true);
		        		
        if (count($reviews) == 0)
		{
            $the_content .= '<p>There are no reviews yet. Be the first to leave yours!</p>';
        } 
		else
		{   

			$this->get_aggregate_reviews($post->ID);
        
			$summary = $this->got_aggregate["text"];       
			$best_score = number_format($this->got_aggregate["max"],1);
			$average_score = number_format($this->got_aggregate["aggregate"],1);
		
			if ($this->options['hreview_type'] == 'product')
			{
				$reviews_content .= '
				<span class="item hproduct" id="hproduct-'.$post->ID.'">
					<span class="wpcr_hide">
						<span class="brand">'.$meta_product_brand.'</span>
						<span class="fn">'.$meta_product_name.'</span>
						<span class="description">'.$meta_product_desc.'</span>
						<span class="identifier">
							<span class="type">SKU</span>
							<span class="value">'.$meta_product_sku.'</span>
						</span>
						<span class="identifier">
							<span class="type">UPC</span>
							<span class="value">'.$meta_product_upc.'</span>
						</span>
						<span class="identifier">
							<span class="type">Model</span>
							<span class="value">'.$meta_product_model.'</span>
						</span>
					</span>
				';
			}
		
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
                        /* do nothing */
                } else {
                        $review->review_title = substr($review->review_text,0,150);
                        $hidesummary = 'wpcr_hide';
                }

                $review->review_text = nl2br($review->review_text);
				
				$custom_fields_unserialized = @unserialize($review->custom_fields);
				if (!is_array($custom_fields_unserialized)) { $custom_fields_unserialized = array(); }
				
				$custom_shown = '';
				foreach ($this->options['field_custom'] as $i => $val) {
					$show = $this->options['show_custom'][$i];
					if ($show == 1 && $custom_fields_unserialized[$val] != '') {
						if ($custom_shown == '') { $custom_shown = '<br />'; }
						$custom_i = "custom_$i";
						$custom_shown .= $val.': '.$custom_fields_unserialized[$val].'&nbsp;|&nbsp;';
					}
				}
				
				$custom_shown = rtrim($custom_shown,"|&nbsp;");
				
				$name_block = ''.
					'<div class="wpcr_fl wpcr_rname">'.
						'<abbr title="'.$this->iso8601(strtotime($review->date_time)).'" class="dtreviewed">'.date("M d, Y",strtotime($review->date_time)).'</abbr>&nbsp;'.
						'<span class="'.$hide_name.'">by</span>&nbsp;'.
						'<span class="reviewer vcard" id="hreview-wpcr-reviewer-'.$review->id.'">'.
							'<span class="fn '.$hide_name.'">'.$review->reviewer_name.'</span>'.
						'</span>'.
						$custom_shown.
					'</div>';
			
				if ($this->options['hreview_type'] == 'product')
				{
					$reviews_content .= '
						<div class="hreview" id="hreview-'.$review->id.'">
							<'.$title_tag.' class="summary '.$hidesummary.'">'.$review->review_title.'</'.$title_tag.'>
							<span class="item" id="hreview-wpcr-hproduct-for-'.$review->id.'" style="display:none;">
								<span class="fn">'.$meta_product_name.'</span>
							</span>
							<div class="wpcr_fl wpcr_sc">
								<abbr class="rating" title="'.$review->review_rating.'"></abbr>
								<div class="wpcr_rating">
									'.$this->output_rating($review->review_rating,false).'
								</div>					
							</div>
							'.$name_block.'
							<div class="wpcr_clear wpcr_spacing1"></div>
							<blockquote class="description"><p>'.$review->review_text.'</p></blockquote>
							<span style="display:none;" class="type">product</span>
							<span style="display:none;" class="version">0.3</span>
						</div>
						<hr />';
				}
				else if ($this->options['hreview_type'] == 'business')
				{
					$reviews_content .= '
                    <div class="hreview" id="hreview-'.$review->id.'">
                        <'.$title_tag.' class="summary '.$hidesummary.'">'.$review->review_title.'</'.$title_tag.'>
                        <div class="wpcr_fl wpcr_sc">
                            <abbr class="rating" title="'.$review->review_rating.'"></abbr>
                            <div class="wpcr_rating">
                                '.$this->output_rating($review->review_rating,false).'
                            </div>					
                        </div>
                        '.$name_block.'
                        <div class="wpcr_clear wpcr_spacing1"></div>
                        <span class="item vcard" id="hreview-wpcr-hcard-for-'.$review->id.'" style="display:none;">
                            <a class="url fn org" href="'.$this->options['business_url'].'">'.$this->options['business_name'].'</a>
                            <span class="tel">'.$this->options['business_phone'].'</span>
							<span class="adr">
								<span class="street-address">'.$this->options['business_street'].'</span>
								<span class="locality">'.$this->options['business_city'].'</span>
								<span class="region">'.$this->options['business_state'].'</span>, <span class="postal-code">'.$this->options['business_zip'].'</span>
								<span class="country-name">'.$this->options['business_country'].'</span>
							</span>
                        </span>
                        <blockquote class="description"><p>'.$review->review_text.'</p></blockquote>
						<span style="display:none;" class="type">business</span>
                        <span style="display:none;" class="version">0.3</span>
                   </div>
				   <hr />';
				}
            }
			
			if ($this->options['hreview_type'] == 'product')
			{
				$reviews_content .= '
				<span class="hreview-aggregate haggregatereview" id="hreview-wpcr-aggregate">
				   <span style="display:none;">
					   <span class="rating">
						 <span class="average">'.$average_score.'</span>
						 <span class="best">'.$best_score.'</span>
					   </span>  
					   <span class="votes">'.$this->got_aggregate["total"].'</span>
					   <span class="count">'.$this->got_aggregate["total"].'</span>
					   <span class="summary">'.$summary.'</span>
					   <span class="item" id="hreview-wpcr-vcard">
							<span class="fn">'.$meta_product_name.'</span>
					   </span>
				   </span>
				</span>';
				$reviews_content .= '</span>'; /* end hProduct */
			}
			else if ($this->options['hreview_type'] == 'business')
			{
				$reviews_content .= '
				<span class="hreview-aggregate" id="hreview-wpcr-aggregate">
				   <span style="display:none;">
						<span class="item vcard" id="hreview-wpcr-vcard">
							<a class="url fn org" href="'.$this->options['business_url'].'">'.$this->options['business_name'].'</a>
							<span class="tel">'.$this->options['business_phone'].'</span>
							<span class="adr">
								<span class="street-address">'.$this->options['business_street'].'</span>
								<span class="locality">'.$this->options['business_city'].'</span>
								<span class="region">'.$this->options['business_state'].'</span>, <span class="postal-code">'.$this->options['business_zip'].'</span>
								<span class="country-name">'.$this->options['business_country'].'</span>
							</span>
						</span>
					   <span class="rating">
						 <span class="average">'.$average_score.'</span>
						 <span class="best">'.$best_score.'</span>
					   </span>  
					   <span class="votes">'.$this->got_aggregate["total"].'</span>
					   <span class="count">'.$this->got_aggregate["total"].'</span>
					   <span class="summary">'.$summary.'</span>
				   </span>
				</span>
				';
			}
        }
        
		$the_content .= $this->show_reviews_form($status_msg);
        $the_content .= $reviews_content;
        $the_content .= $this->pagination($total_reviews);
        if ($this->options['support_us'] == 1) {
            $the_content .= '<div class="wpcr_clear wpcr_power">Powered by <strong><a href="http://www.gowebsolutions.com/plugins/wp-customer-reviews/">WP Customer Reviews</a></strong></div>';
        }
        $the_content .= '</div>';
		
		$the_content .= $this->aggregate_footer(); /* check if we need to show something in the footer also */
		
		return $original_content.$the_content;
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
		
		$custom_fields = array(); /* used for insert as well */
		$custom_count = count($this->options['field_custom']); /* used for insert as well */
		for ($i = 0; $i < $custom_count; $i++)
		{
			$custom_fields[$i] = $this->options['field_custom'][$i];
		}
		
		foreach ($this->options['ask_custom'] as $i => $val) {
            if ($val == 1) {
				if ($this->options['require_custom'][$i] == 1) { $req = '*'; } else { $req = ''; }
				$custom_i = "custom_$i";
                $fields .= '<tr><td><label for="custom_'.$i.'" class="comment-field">'.$custom_fields[$i].': '.$req.'</label></td><td><input class="text-input" type="text" id="custom_'.$i.'" name="custom_'.$i.'" maxlength="150" value="'.$this->p->$custom_i.'" /></td></tr>';
            }
        }
        
        $some_required = '';
        $req_js = "<script type='text/javascript'>\n<!--\n";
        foreach ($this->options['require_fields'] as $col => $val) {
            if ($val == 1) {
                $req_js .= "wpcr_req.push('$col');";
                $some_required = '<small>* Required Field</small>';
            }
        }      
		
		foreach ($this->options['require_custom'] as $i => $val) {
            if ($val == 1) {
                $req_js .= "wpcr_req.push('custom_$i');";
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

    function add_review($pageID) {
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
                
        /* some sanitation */
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
		
		$custom_fields = array(); /* used for insert as well */
		$custom_count = count($this->options['field_custom']); /* used for insert as well */
		for ($i = 0; $i < $custom_count; $i++)
		{
			$custom_fields[$i] = $this->options['field_custom'][$i];
		}
		
		foreach ($this->options['require_custom'] as $i => $val) {
            if ($val == 1) {
				$custom_i = "custom_$i";
                if ($this->p->$custom_i == '') {
                    $nice_name = $custom_fields[$i];
                    $errors .= 'You must include your '.$nice_name.'.<br />';
                }
            }
        }
        
        if (!preg_match('/^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/', $this->p->femail)) {
            $errors .= 'The email address provided is not valid.<br />';
        }
        
        if (!preg_match('/^\S+:\/\/\S+\.\S+.+$/', $this->p->fwebsite)) {
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
		
		$custom_insert = array();
		for ($i = 0; $i < $custom_count; $i++)
		{						
			if ($this->options['ask_custom'][$i] == 1) {
				$name = $custom_fields[$i];
				$custom_i = "custom_$i";
				$custom_insert["$name"] = ucfirst($this->p->$custom_i);
			}
		}
		$custom_insert = serialize($custom_insert);
        
        $query = $wpdb->prepare("INSERT INTO `$this->dbtable` 
                (date_time, reviewer_name, reviewer_email, reviewer_ip, review_title, review_text, status, review_rating, reviewer_url, custom_fields, page_id) 
                VALUES (%s, %s, %s, %s, %s, %s, %d, %d, %s, %s, %d)",
                $date_time, $this->p->fname, $this->p->femail, $ip, $this->p->ftitle, $this->p->ftext, 0, $this->p->frating, $this->p->fwebsite, $custom_insert, $pageID);
        
        $wpdb->query($query);
        
        @wp_mail( get_bloginfo('admin_email'), "WP Customer Reviews: New Review Posted on ".date('m/d/Y h:i'), "A new review has been posted for ".$this->options['business_name']." via WP Customer Reviews. \n\nYou will need to login to the admin area and approve this review before it will appear on your site.");
        
        /* returns false for no error */
        return array(false,'<div style="color:#c00;font-weight:bold;padding-bottom:15px;padding-top:15px;">Thank you for your comments. All submissions are moderated and if approved, yours will appear soon.</div>');
    }
    
    function deactivate() {
		global $WPCustomerReviewsAdmin;
		$this->include_admin(); /* include admin functions */
        $WPCustomerReviewsAdmin->notify_activate($this->options['act_email'],2);
    }
    
    function js_redirect($url,$cookie = array()) {
		/* we do not html comment script blocks here - to prevent any issues with other plugins adding content to newlines, etc */
        $out = "<div style='clear:both;text-align:center;padding:10px;'>
		Processing... Please wait...
		<script type='text/javascript'>";
	    foreach ($cookie as $col => $val) {
			$val = preg_replace("/\r?\n/", "\\n", addslashes($val));
			$out .= "document.cookie=\"$col=$val\";\n";
	    }
		$out .= "window.location='$url';\n";
		$out .= "</script>\n";
		$out .= "</div>";
		return $out;
    }
    	
    function init() { /* used for admin_init also */ 		        
        $this->make_p_obj(); /* make P variables object */
        $this->get_options(); /* populate the options array */		
        $this->check_migrate(); /* call on every instance to see if we have upgraded in any way */
		
		$this->page = intval($this->p->wpcrp);
        if ($this->page < 1) { $this->page = 1; }
    }
    
    function activate() {
        global $wpdb;
        
        $existing_tbl = $wpdb->get_var("SHOW TABLES LIKE '$this->dbtable'");
        if ( $existing_tbl != $this->dbtable ) {
			global $WPCustomerReviewsAdmin;
			$this->include_admin(); /* include admin functions */
            $WPCustomerReviewsAdmin->createReviewtable();
        }
        
        add_option('wpcr_gotosettings', true); /* used for redirecting to settings page upon initial activation */
    }
	
	function include_admin() {
		global $WPCustomerReviewsAdmin;
		require_once($this->getplugindir.'wp-customer-reviews-admin.php'); /* include admin functions */
	}
    
    function admin_init() {
		global $WPCustomerReviewsAdmin;
		$this->include_admin(); /* include admin functions */		
		$WPCustomerReviewsAdmin->real_admin_init();
    }
    
    function getpluginurl() {
        return trailingslashit(plugins_url(basename(dirname(__FILE__))));
    }
	
	function getplugindir() {
		return trailingslashit(WP_PLUGIN_DIR.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)));
	}
}

if (!defined('IN_WPCR')) {
	global $WPCustomerReviews;
	$WPCustomerReviews = new WPCustomerReviews();
	register_activation_hook(__FILE__, array( &$WPCustomerReviews, 'activate' ));
	register_deactivation_hook( __FILE__, array( &$WPCustomerReviews, 'deactivate' ));
}
?>