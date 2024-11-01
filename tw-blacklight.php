<?php
/*
Plugin Name: BlackLight
Description: Highlights comments on the Edit Comments tab that were caught because of the Comment Blacklist
Author: Alex Shiels
Version: 0.2
Author URI: http://thresholdstate.com/
*/

function tw_blacklight_init() {
	if ( is_admin() && current_user_can('moderate_comments') ) {
		add_filter('comment_text', 'tw_blacklight_highlight', 8);
		add_filter('get_comment_author_url', 'tw_blacklight_remember_url');
		add_filter('get_comment_author_IP', 'tw_blacklight_remember_ip');
		add_filter('comment_email', 'tw_blacklight_remember_email', 0);
		add_filter('comment_author', 'tw_blacklight_highlight');
		add_filter('comment_row_actions', 'tw_blacklight_display_status', 10, 2);
	}
}

add_action('admin_init', 'tw_blacklight_init');

// it's not possible to filter and display some fields, because exactly the same filters are used when displaying
// items and when constructing URLs, so our markup would break those links.  So instead we have to remember
// which of those fields triggered the blacklist, and display that info below the conmment.
function tw_blacklight_remember_ip($ip) {
	return tw_blacklight_remember_thing('IP', $ip);
}

function tw_blacklight_remember_url($url) {
	return tw_blacklight_remember_thing('link', $url);
}

function tw_blacklight_remember_email($email) {
	return tw_blacklight_remember_thing('email', $email);
}

function tw_blacklight_remember_thing($type, $value) {
	global $comment, $tw_blacklight_comments;
	
	$highlighted = tw_blacklight_highlight($value);
	if ( $highlighted != $value ) {
		$tw_blacklight_comments[$comment->comment_ID][$type] = $highlighted;
	}
	
	return $value; // the untouched original for the filter chain
}

// this function sucks mops, but it's the only way I can find to do it.
// The problem is that the code that prints rows on the comment-edit tab, provides no actions that could be used for
// cleanly adding extra information.  The only way to do that is to hook into a filter (which might be used elsewhere
// in different contexts), echo your extra info at just the right time, and hope that it doesn't break some other
// plugin or tab.
// In this case we'll take advantage of the fact that the 'comment_row_actions' filter is called before echoing
// the <div class="row-actions"> container for the Spam/Delete/etc buttons.  Provided that filter is never moved,
// we should be able to echo our content at that point and have it appear just above those row actions.  But of course
// that could break in future.
function tw_blacklight_display_status($actions, $comment) {
	global $tw_blacklight_comments;
	
	if ( !empty($tw_blacklight_comments[$comment->comment_ID]) ) {
		echo '<div style="color:#777;" class="tw-blacklight-status"><ul>';
		foreach ( $tw_blacklight_comments[$comment->comment_ID] as $type => $value ) {
			echo '<li style="font-size:10px;">';
			echo sprintf( __('Blacklisted %s: %s'), $type, $value);
			echo '</li>';
		}
		echo '</ul></div>';
	}
	
	return $actions;
}


function tw_blacklight_replace($in) {
	return '<span class="tw-blacklight" style="color:#fff;background-color:#000;" title="'.__('this text matches a word in your Comment Blacklist').'">' . $in[1] . '</span>';
}

function tw_blacklight_replace_char($m) {
	$char = $m[1];
	if ( $char < 128 && $char != 38 )
		return tw_blacklight_replace( $m[0] );
}

function tw_blacklight_filter_words($word) {
	$word = trim($word);
	$word = preg_quote($word, '#');
	return $word;
}

// adapted from wp_blacklist_check()
function tw_blacklight_highlight($text) {

	// this mimics the ampersand encoding check in wp_blacklist_check()
	$text = preg_replace_callback('/&#(\d+);/', 'tw_blacklight_replace_char', $text);


	$mod_keys = trim( get_option('blacklist_keys') );
	if ( '' == $mod_keys )
		return $text; // If moderation keys are empty
		
		
	$words = array_filter( array_map('tw_blacklight_filter_words', explode("\n", $mod_keys) ) );

	if ( $words ) {
		$wordlist = join('|', $words);
		
		$text = preg_replace_callback('#('.$wordlist.')#i', 'tw_blacklight_replace', $text);

	}

	return $text;
}

?>
