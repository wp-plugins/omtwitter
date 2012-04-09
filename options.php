<?php
/**
 * Twitter plugin options and settings
 * @author Roman Ozana
 */
class omTwitterOptions
{
  const tweet_id = 'tweet_id';
  const tweet_json = 'tweet_json';

  /** @var omTwitterOptions */
  private static $instance = null;

  /** @var string|null */
  public $consumer_key = null;
  /** @var string|null */
  public $consumer_secret = null;
  /** @var string|null */
  public $access_token = null;
  /** @var string|null */
  public $access_token_secret = null;

  /** @var int|null */
  public $nick = null;
  /** @var int|null */
  public $user = null;
  /** @var int|null */
  public $category = null;
  /** @var string */
  public $posttype = 'tweet';
  /** @var int|null */
  public $postformat = null;
  /** @var bool show post type in menu */
  public $show_post_type = true;

  /**
   * Can save options
   * @static
   * @return bool
   */
  public static function canUpdate()
  {
    return isset($_POST['update']) && $_POST['update'] == __CLASS__;
  }

  public static function update()
  {
    return update_option(__CLASS__, self::getOptions());
  }

  /**
   * Load options from DB
   * @return omTwitterOptions
   */
  public static function getOptions()
  {
    if (is_null(self::$instance)) self::$instance = get_option(__CLASS__, new self);

    if (self::canUpdate()) {
      // all values
      foreach ($_POST as $name => $value) if (property_exists(self::$instance, $name)) self::$instance->{$name} = $value;

      // checkboxes
      self::$instance->show_post_type = isset($_POST['ch_show_post_type']);
    }

    return self::$instance;
  }

}
