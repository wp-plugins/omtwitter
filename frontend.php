<?php
/**
 * Return last Tweet as HTML
 * @return string
 */
function get_last_tweet()
{
  global $omTwitter;
  return $omTwitter->getLastTweet();
}

/**
 * Get date / time xxx hours ago like Twitter
 * @param $post
 * @param string $before
 * @param int $max
 * @return string
 */
function get_time_ago($post, $before = 'before ', $max = 14)
{
  $to = time();
  $from = get_post_time('G', true, $post);

  if (round((int)abs($to - $from) / $max) > 86400) {
    return apply_filters('get_the_date', date(get_option('date_format'), $from), get_option('date_format'));
  } else {
    return $before . human_time_diff($from, $to);
  }
}

/**
 * Return post meta about tweet
 * @param $post
 * @return array|mixed|null
 */
function get_tweet_meta(& $post)
{
  $meta = get_post_meta($post, omTwitterOptions::tweet_json, true);
  return ($meta) ? simplexml_load_string('<?xml version="1.0"?>' . $meta) : null;
}

/**
 * Return reply to tweet link
 * @param $post
 * @return string
 */
function get_tweet_reply_link(& $post) {
  if ($id = get_post_meta($post, omTwitterOptions::tweet_id, true)) {
    return sprintf('<a href="http://twitter.com/intent/tweet?in_reply_to=%s">%s</a>', $id, __('Reply', 'omt'));
  }
  return '';
}
/**
 * Return retweet link
 * @param $post
 * @return string
 */
function get_tweet_retweet_link(& $post) {
  if ($id = get_post_meta($post, omTwitterOptions::tweet_id, true)) {
     return sprintf('<a href="http://twitter.com/intent/retweet?tweet_id=%s">%s</a>', $id, __('Retweet', 'omt'));
   }
   return '';
}
/**
 * Return favorite link
 * @param $post
 * @return string
 */
function get_tweet_favorite_link(& $post) {
  if ($id = get_post_meta($post, omTwitterOptions::tweet_id, true)) {
     return sprintf('<a href="http://twitter.com/intent/favorite?in_reply_to=%s">%s</a>', $id, __('Favorite', 'omt'));
   }
   return '';
}