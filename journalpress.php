<?php
/*
Plugin Name: JournalPress
Plugin URI: https://github.com/alisinfinite/journalpress/
Description: Mirrors your WordPress blog to any number of LiveJournal-based external journalling sites. Supports per-journal-per-post userpics and custom permission levels via the separate WP-Flock plugin. v1.0 is is an almost total rewrite, so please make sure to check your journals and settings are still correct!
Version: 1.2
Author: Alis
Author URI: https://alis.me/
*/
defined('ABSPATH') or die(':(');


if(!class_exists('journalpress')):
class journalpress {

//= VARIABLES ===================================================================//
  private $admin;
  private $install;
  private $meta;
  private $posts;
  

//= CLASS AND WORDPRESS STUFF ===================================================//
  // constructor
  public function __construct() { 
    // other thingies
    
    $this->doincludes();
    
    $this->canadmin();
    $this->canpost();
    
    $this->catchposts();
    
    return;
  }
  
  private function canadmin(){ 
    if(is_admin()){
      $this->admin = new jpadmin();
      $this->install = new jpinstall();
      
      // build us up...
      register_activation_hook(plugin_basename(__FILE__), array($this->install, 'init'));
      register_deactivation_hook(plugin_basename(__FILE__), array($this->install, 'deinit'));
      
      // menu pages
      add_action('admin_menu', array($this->admin, 'admin_menu'));
      
      // plugin links
      add_filter('plugin_action_links_'. plugin_basename(__FILE__), array($this->admin, 'admin_links'));
      
      // init stuff
      add_action('admin_init', array($this->admin, 'admin_init'));
    }
  }
  
  // this should be based on user capability, not just whether or not we're on the admin page
  // but we can't access those functions from where this is getting called soooo... thanks, WordPress!
  private function canpost(){ 
    if(is_admin()){
      $this->meta = new jpmeta();
  
      // meta box
      add_action('add_meta_boxes_post', array($this->meta, 'add_meta'), 10, 2);
      //add_action('save_post', array($this->meta, 'save_meta'), 5);
      add_action('transition_post_status', array($this->meta, 'save_meta'), 1, 3);
    }  
  }
  
  private function catchposts(){
    $this->posts = new jpposts();
    
    add_action('transition_post_status', array($this->posts, 'dopost'), 10, 3);
  }
  
  private function doincludes(){ 
    // wp xmlrpc library
    if(defined('ABSPATH')){
      require_once(ABSPATH . WPINC .'/class-IXR.php' );
      require_once(ABSPATH . WPINC .'/class-wp-http-ixr-client.php');
    }
    
    // include our other files
    if(is_admin()){
      require_once(dirname(__FILE__) .'/admin/jpadmin.class.php');
      require_once(dirname(__FILE__) .'/admin/jpinstall.class.php');
      require_once(dirname(__FILE__) .'/admin/jpsettings.class.php');
      require_once(dirname(__FILE__) .'/admin/jpmirrors.class.php');
      require_once(dirname(__FILE__) .'/admin/jpmeta.class.php');
    }
    
    require_once(dirname(__FILE__) .'/lib/jpposts.class.php');
    require_once(dirname(__FILE__) .'/lib/lj.class.php');
  }
}
endif;
//===============================================================================//

// initalise the class
if(class_exists('journalpress'))
  { $journalpress = new journalpress(); }