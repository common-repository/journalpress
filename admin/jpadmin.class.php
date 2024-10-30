<?php
defined('ABSPATH') or die(':(');

if(!class_exists('jpadmin')):
class jpadmin {
  // variables
  private $settings;
  private $mirrors;
  
  private $options;
  
  // constructor
  public function __construct() {
    $this->options = get_option('jp_config');
    
    $this->settings = new jpsettings();
    $this->mirrors = new jpmirrors();
    
    return;
  }

  // what admin pages do we want?
  public function admin_menu(){
    add_options_page('JournalPress configuration', 'JournalPress', 'manage_options', 'journalpress', array($this->settings, 'admin_page'));
    add_options_page('JournalPress mirrors', 'JP Mirrors', 'manage_options', 'jp-journals', array($this->mirrors, 'journals_page'));
  }
  
  // initiate our admin page
  public function admin_init() {
    register_setting('jp_settings_group', 'jp_config', array($this->settings, 'jp_settings_validate'));
    
    add_settings_section('jp_linkback', 'Linkback settings', array($this->settings, 'jp_linkback'), 'journalpress');
    add_settings_field('header_loc', 'Crosspost linkback location', array($this->settings, 'jp_header_loc'), 'journalpress', 'jp_linkback');
    //add_settings_field('custom_name', 'Custom blog title', array($this->settings, 'jp_custom_name'), 'journalpress', 'jp_linkback');
    add_settings_field('custom_header', 'Linkback format', array($this->settings, 'jp_custom_header'), 'journalpress', 'jp_linkback');
    
    add_settings_section('jp_posting', 'Posting and commenting', array($this->settings, 'jp_posting'), 'journalpress');
    add_settings_field('privacy', 'Default privacy level', array($this->settings, 'jp_privacy'), 'journalpress', 'jp_posting');
    add_settings_field('privacyp', 'Privacy level: Private posts', array($this->settings, 'jp_privacyp'), 'journalpress', 'jp_posting');
    add_settings_field('privacyl', 'Privacy level: Password-protected posts', array($this->settings, 'jp_privacyl'), 'journalpress', 'jp_posting');
    add_settings_field('comments', 'Comment options', array($this->settings, 'jp_comments'), 'journalpress', 'jp_posting');
    add_settings_field('cat', 'Mirror categories', array($this->settings, 'jp_cat'), 'journalpress', 'jp_posting');
    add_settings_field('tag', 'Mirror tags', array($this->settings, 'jp_tag'), 'journalpress', 'jp_posting');
    add_settings_field('more', 'Handling of <kbd>&lt;!--more--&gt;</kbd>', array($this->settings, 'jp_more'), 'journalpress', 'jp_posting');
    
    return;
  }
  
  public function admin_links($links){
    $links[] = '<a href="'. esc_url(get_admin_url(null, 'options-general.php?page=journalpress')) .'">Settings</a>';
    $links[] = '<a href="'. esc_url(get_admin_url(null, 'options-general.php?page=jp-journals')) .'">Journals</a>';
    return $links; 
  }
}
endif;