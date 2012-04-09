<?php
/*
  Plugin Name: omTwitter
  Plugin URI: http://www.nabito.net/tweets/
  Description: Plugin for simple periodic backup of <a href="http://twitter.com">Tweets</a>. Can store your Tweet to separate category or can mix them with your blog posts. Compare <a href="https://twitter.com/#!/OzzyCzech">@OzzyCzech</a> and <a href="http://www.nabito.net/tweets">my blog</a>.
  Version: v2.0
  Author: Roman Ožana
  Author URI: http://www.omdesign.cz/
 */

/*  Copyright 2012, Roman Ozana, (email : ozana@omdesign.cz)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * Secure check
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

if (!class_exists('WP')) {
  header('Status: 403 Forbidden');
  header('HTTP/1.1 403 Forbidden');
  exit;
}

/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * Includes
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

if (!ini_get('date.timezone')) date_default_timezone_set('Europe/Prague');

require_once __DIR__ . '/twitter/twitter.class.php';
require_once __DIR__ . '/options.php'; // omTwitterOptions


/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * Twitter reader and archiver
 * @see http://codex.wordpress.org/Post_Formats
 * @see http://codex.wordpress.org/Function_Reference/wp_schedule_event
 * @see http://codex.wordpress.org/Function_Reference/add_post_type_support
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
class omTwitter
{
  /** @var Twitter */
  private $twitter;
  /** @var \wpdb */
  private $wpdb;

  /**
   * @param \Twitter $twitter
   * @param \wpdb $wpdb
   */
  public function __construct(Twitter $twitter, $wpdb)
  {
    $this->twitter = $twitter;
    $this->wpdb = $wpdb;
  }

  /**
   * Init plugin with default data
   */
  public function init()
  {
    // registrace hooku
    register_uninstall_hook(__FILE__, array($this, 'uninstall'));

    // aktivace a deaktivace pluginu
    add_action('tweets_backup_cron_hook', array($this, 'backup'));
    register_activation_hook(__FILE__, array($this, 'activation'));
    register_deactivation_hook(__FILE__, array($this, 'deactivation'));

    // inicializace custom post type Tweet
    add_action('init', array(__CLASS__, 'initTweetCustomPostType'));

    // is cache writable ?
    add_action('admin_notices', function ()
    {
      if (!is_writable(Twitter::$cacheDir))
        echo '<div class="error"><p><strong>' . sprintf(_('Make folder %s writable.'), realpath(Twitter::$cacheDir)) . '</strong></p></div>';
    });

    // clean cache
    foreach (new RecursiveDirectoryIterator(Twitter::$cacheDir) as $file) {
      /** @var SplFileInfo $file */
      if ($file->getMTime() + Twitter::$cacheExpire < time()) unlink((string)$file);
    }

    // admin menu
    $me = $this; // < PHP 5.4 can use $this
    add_action('admin_menu', function() use ($me)
    {
      add_options_page('omTwitter setup', 'omTwitter', 'manage_options', 'omTwitterOptions', array($me, 'options'));
    });

    //if (is_admin())
    add_action('wp_ajax_tweetBackup', array($this, 'ajaxTweetBackup'));
  }

  /**
   * Plugin uninstalation
   */
  public function uninstall()
  {
    wp_clear_scheduled_hook('tweets_backup_cron_hook');
  }

  /**
   * Plugin activation
   */
  public function activation()
  {
    if (!wp_next_scheduled('tweets_backup_cron_hook')) {
      wp_schedule_event(time(), 'hourly', 'tweets_backup_cron_hook');
    }

    self::initTweetCustomPostType();

    flush_rewrite_rules(false); // custom post type rewrite
  }

  /**
   * Plugin deactivation
   */
  public function deactivation()
  {
    wp_clear_scheduled_hook('tweets_backup_cron_hook');
  }

  /**
   * Custom post type init
   * @see http://justintadlock.com/archives/2010/07/10/meta-capabilities-for-custom-post-types
   * @see http://codex.wordpress.org/Function_Reference/register_post_type
   */
  public static function initTweetCustomPostType()
  {
    $options = omTwitterOptions::getOptions();

    $labels = array(
      'name' => _x('Tweets', 'post type general name', 'omt'),
      'singular_name' => _x('Tweets', 'post type singular name', 'omt'),
      'add_new' => _x('Add New', 'tweet', 'omt'),
      'add_new_item' => __('Add New Tweet', 'omt'),
      'edit_item' => __('Edit Book', 'omt'),
      'new_item' => __('New Tweets', 'omt'),
      'all_items' => __('All Tweets', 'omt'),
      'view_item' => __('View Tweets', 'omt'),
      'search_items' => __('Search Tweets', 'omt'),
      'not_found' => __('No tweets found', 'omt'),
      'not_found_in_trash' => __('No tweets found in Trash', 'omt'),
      'parent_item_colon' => '',
      'menu_name' => __('Tweets', 'omt')

    );

    $args = array(
      'labels' => $labels,
      'public' => true,
      'publicly_queryable' => true,
      'exclude_from_search' => false,
      'show_ui' => true,
      'show_in_menu' => (bool)$options->show_post_type,
      'query_var' => true,
      'rewrite' => array(
        'slug' => 'tweets',
        'with_front' => false,
      ),
      'capability_type' => 'post',
      'has_archive' => 'tweets',
      'hierarchical' => false,
      'menu_position' => null,
      'supports' => array('title', 'author', 'editor', 'post-formats')
    );

    register_post_type('tweet', $args);
  }

  /**
   * Setup options
   * @return mixed
   */
  public function options()
  {
    //dump($_POST);
    $options = omTwitterOptions::getOptions();
    $message = null;
    $user = null;

    if (omTwitterOptions::canUpdate()) {
      omTwitterOptions::update();
      $message = __('Twitter settings have been saved.', 'omt');
    }

    // try load user info
    try {
      if (!empty($options->nick)) $user = $this->twitter->loadUserInfo($options->nick);
      if ($user && $message) $message .= ' ' . __('Visit user information bellow.', 'omt');
    } catch (\TwitterException $e) {
      $message = $e->getMessage();
    }

    // backup activate
    if (isset($_GET['action']) && $_GET['action'] = 'backup') {
      if (isset($user)) {
        $statuses_count = ($user->statuses_count > 3600) ? 3600 : $user->statuses_count;
        $pages = $statuses_count === NULL ? NULL : (int)ceil($statuses_count / 50);
        return include __DIR__ . '/backup.phtml';
      } else {
        $message = __('Please setup application, before backup', 'omt');
      }
    }

    return include __DIR__ . '/settings.phtml';
  }

  /**
   * Run full Tweet backup
   */
  public function ajaxTweetBackup()
  {
    if (isset($_POST['page'])) {
      $page = (int)$_POST['page'];
      $this->backup(50, $page); // download 50 requests per page

      $out = array(
        'page' => $page,
        'message' => sprintf(__('Download %s tweets', 'omt'), $page * 50),
      );

      echo json_encode($out);
    }

    die();
  }

  /**
   * Create links inside Tweet text
   * @param string $text
   * @return string
   */
  public static function twittery($text)
  {
    $text = preg_replace("#(^|[\n ])([\w]+?://[\w]+[^ \"\n\r\t< ]*)#", "\\1<a href=\"\\2\" target=\"_blank\">\\2</a>", $text);
    $text = preg_replace("#(^|[\n ])((www|ftp)\.[^ \"\t\n\r< ]*)#", "\\1<a href=\"http://\\2\" target=\"_blank\">\\2</a>", $text);
    $text = preg_replace("/@(\w+)/", "<a href=\"http://twitter.com/\\1\" target=\"_blank\">@\\1</a>", $text);
    $text = preg_replace("/#(\w+)/", "<a href=\"http://twitter.com/#!/search/%23\\1\" target=\"_blank\">#\\1</a>", $text);

    return $text;
  }

  /**
   * Return last Tweet HTML
   * @return string
   */
  public function getLastTweet()
  {
    try {
      $last = $this->twitter->load(Twitter::ME, 1);
      $time = human_time_diff(date('U', strtotime($last->status->created_at)));
      return self::twittery($last->status->text) . " <small>$time</small>";
    } catch (Exception $e) {
      return $e->getMessage();
    }
  }


  /**
   * Backup my Tweets
   * @param int $load
   * @param int $page
   */
  public function backup($load = 20, $page = 0)
  {
    $options = omTwitterOptions::getOptions();

    try {

      // load tweets from Twitter
      $tweets = $this->twitter->load(Twitter::ME, $load, $page);

      foreach ($tweets as $tweet) {
        // check if Tweet already exists
        $query = $this->wpdb->prepare("
          SELECT p.`id` FROM `wp_posts` p
          INNER JOIN `wp_postmeta` m ON p.`id` = m.`post_id`
          WHERE m.`meta_key` = %s AND m.`meta_value` = %s", omTwitterOptions::tweet_id, $tweet->id);

        if ($this->wpdb->get_var($query)) continue; // found something ? continue to next tweet

        // find all hash tags
        preg_match_all("/#(\S*\w)/i", $tweet->text, $tags);

        // prepare new Post
        $post = array(
          'comment_status' => 'closed',
          'ping_status' => 'closed',
          'post_author' => $options->user,
          'post_category' => array($options->category),
          'post_content' => (string)$tweet->text,
          'post_date' => date('Y-m-d H:i:s', strtotime($tweet->created_at)),
          'post_date_gmt' => date('Y-m-d H:i:s', strtotime($tweet->created_at)),
          'post_status' => 'publish',
          'post_title' => (string)$tweet->text,
          'post_type' => $options->posttype,
          'tags_input' => implode(', ', $tags[1]),
        );

        // save post and metadoata
        $id = wp_insert_post($post);

        // set post format
        if ($options->postformat) set_post_format($id, 'status');

        // and add Twitter id to META
        update_post_meta($id, omTwitterOptions::tweet_id, (string)$tweet->id);
        update_post_meta($id, omTwitterOptions::tweet_json, $tweet->asXML());
      }
    } catch (Exception $e) {

    }
  }
}

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

// options
$options = get_option('omTwitterOptions', new omTwitterOptions());
/** @var $options omTwitterOptions */

// Check if cache dir exists
if (!is_dir(__DIR__ . '/cache')) mkdir(__DIR__ . '/cache', 0777);

// Twitter connection
Twitter::$cacheDir = __DIR__ . '/cache';
$twitter = new Twitter(
  $options->consumer_key,
  $options->consumer_secret,
  $options->access_token,
  $options->access_token_secret
);

// plugin
$omTwitter = new omTwitter($twitter, $wpdb);
$omTwitter->init();
//$omTwitter->backup();

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

include __DIR__ . '/frontend.php';

/*
 *
Custom cron shedule

if (!wp_next_scheduled('tweets_backup_cron_hook')) {
  wp_schedule_event(time(), 'tweetbackupperiod', 'tweets_backup_cron_hook');
}

function own_cron_periods( $schedules ) {
  // backup every 30 sec
	$schedules['tweetbackupperiod'] = array('interval' => 30, 'display' => __('Once Daily'));
	return $schedules;
}
add_filter('cron_schedules', 'own_cron_periods');

*/
