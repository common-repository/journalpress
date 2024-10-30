<?php
defined('ABSPATH') or die(':(');

if(!class_exists('jpinstall')):
class jpinstall {
  private $dbversion = '0.4';
  
  public function init() {
    // default install options
    // migrate from old values, if they exist
    if(!get_option('jp_config')){ $this->default_options(); }

    // create our database
    $this->db_install();

    return;
  }
  
  // TODO: cleanup, probably
  public function deinit() {
    return;
  }
  
  
  // default options
  private function default_options(){
    $options = array(
        'header_loc'    => get_option('jp_header_loc') ? get_option('jp_header_loc') : 1,
        'custom_name'   => get_option('jp_custom_name') ? get_option('jp_custom_name') : '',
        'custom_header' => get_option('jp_custom_header') ? get_option('jp_custom_header') : '<p style="text-align: right"><small>Mirrored from <a href="[permalink]" title="Read original post.">[blog_name]</a>.</small></p>',
        'privacy'       => get_option('jp_privacy') ? get_option('jp_privacy') : 'public',
        'privacyp'      => get_option('jp_privacyp') ? get_option('jp_privacyp') : 'noxp',
        'privacyl'      => get_option('jp_privacyl') ? get_option('jp_privacyl') : 'friends',
        'comments'      => get_option('jp_comments') ? get_option('jp_comments') : 1,
        'cat'           => get_option('jp_cat') ? get_option('jp_cat') : 0,
        'tag'           => get_option('jp_tag') ? get_option('jp_tag') :  1,
        'more'          => get_option('jp_more') ? get_option('jp_more') : 'link',
        'journals'      => array()
      );
      
      // delete old options
      delete_option('jp_custom_name');
    	delete_option('jp_privacy');
    	delete_option('jp_privacyl');
    	delete_option('jp_privacyp');
    	delete_option('jp_comments');
    	delete_option('jp_tag');
    	delete_option('jp_cat');
    	delete_option('jp_more');
    	delete_option('jp_header_loc');
    	delete_option('jp_custom_header');
            
      // add our options
      add_option('jp_config', $options);
  }

  // database stuff
  private function db_install(){
    global $wpdb;
  
  	$jpmirrors = $wpdb->prefix . 'jp_journals';
  	$charset_collate = $wpdb->get_charset_collate();
  	
  	$sql = "CREATE TABLE " . $jpmirrors . " (
             `journalID` tinyint(3) unsigned NOT NULL auto_increment,
             `journalServer` varchar(150) NOT NULL,
             `journalUser` varchar(30) NOT NULL,
             `journalPass` varchar(32) NOT NULL,
             `journalComm` varchar(30) NOT NULL,
             `journalUse` enum('yes','ask','no') NOT NULL default 'ask',
             `journalPics` text,
             `journalPicDefault` varchar(255) NULL,
             PRIMARY KEY  (`journalID`),
             KEY `journalServer` (`journalServer`,`journalUser`,`journalComm`)
		       ) $charset_collate;";
  
  	require_once(ABSPATH .'wp-admin/includes/upgrade.php');
  	dbDelta($sql);
  
  	add_option('jp_dbversion', $this->dbversion);
  }
}
endif;