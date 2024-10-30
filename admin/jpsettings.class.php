<?php
defined('ABSPATH') or die(':(');

if(!class_exists('jpsettings')):
class jpsettings {

  private $options;
  
  // constructor
  public function __construct() {
    $this->options = get_option('jp_config');
    
    return;
  }

  // output our admin page
  public function admin_page(){
    echo '<div class="wrap">'.
         '<h1>JournalPress configuration</h1>'.
         '<p>Please note that changes made here only affect future blog posts; they will not update old entries.</p>'.
         '<form action="options.php" method="post">';
         
    settings_fields('jp_settings_group');
    do_settings_sections('journalpress');
    submit_button();
    
    echo '</form>'.
         '</div>';
  }
  
  // section callbacks
  public function jp_linkback(){ return; }
  public function jp_posting(){ return; }
  
  // setting field callbacks
  public function jp_header_loc(){
    echo '<label><input name="jp_config[header_loc]" type="radio" value="0"'. checked($this->options['header_loc'], 0, false) .'> Above post</label><br>'.
				 '<label><input name="jp_config[header_loc]" type="radio" value="1"'. checked($this->options['header_loc'], 1, false) .'> Below post</label>';
  }
  
  public function jp_custom_name(){
    echo '<input name="jp_config[custom_name]" type="text" id="custom_name" value="'. esc_attr($this->options['custom_name']) .'" size="40">'.
		     '<p class="setting-description">If you wish your linkbaks to use a custom blog title, enter it here. Otherwise, the default name of your blog will be used ('. get_bloginfo('name') .').</p>';
  }
  
  public function jp_custom_header(){
    echo '<textarea name="jp_config[custom_header]" id="custom_header" class="large-text code" rows="5" cols="50">'. esc_textarea($this->options['custom_header']) .'</textarea>'.
         '<p class="description">If you don\'t like the default linkback format, specify your own here. For flexibility, you can choose from a series of case-sensitive substitution strings, listed below:</p>'.
         '<dl>'.
         '  <dt class="description"><kbd>[blog_name]</kbd></dt>'.
         '  <dd class="description">The title of your blog, as specified above.</dd>'.
         '  <dt class="description"><kbd>[blog_link]</kbd></dt>'.
         '  <dd class="description">The URL of your blog\'s homepage.</dd>'.
         '  <dt class="description"><kbd>[permalink]</kbd></dt>'.
         '  <dd class="description">The blog\'s permalink for your post.</dd>'.
         '  <dt class="description"><kbd>[comments_link]</kbd></dt>'.
         '  <dd class="description">The URL for comments. Generally this is the permalink URL with #comments on the end.</dd>'.
         '</dl> ';
  }
  
  public function jp_privacy(){
    echo '<label><input name="jp_config[privacy]" type="radio" value="public"'. checked($this->options['privacy'], 'public', false) .'> Public</label><br>'.
					'<label><input name="jp_config[privacy]" type="radio" value="private"'. checked($this->options['privacy'], 'private', false) .'> Private</label><br>'.
					'<label><input name="jp_config[privacy]" type="radio" value="friends"'. checked($this->options['privacy'], 'friends', false) .'> Friends only</label>';
  }

  public function jp_privacyp(){
    echo '<label><input name="jp_config[privacyp]" type="radio" value="public"'. checked($this->options['privacyp'], 'public', false) .'> Public</label><br>'.
				 '<label><input name="jp_config[privacyp]" type="radio" value="private"'. checked($this->options['privacyp'], 'private', false) .'> Private</label><br>'.
				 '<label><input name="jp_config[privacyp]" type="radio" value="friends"'. checked($this->options['privacyp'], 'friends', false) .'> Friends only</label><br>'.
				 '<label><input name="jp_config[privacyp]" type="radio" value="noxp"'. checked($this->options['privacyp'], 'noxp', false) .'> Do not crosspost</label>';
  }
  
  public function jp_privacyl(){
    echo '<label><input name="jp_config[privacyl]" type="radio" value="public"'. checked($this->options['privacyl'], 'public', false) .'> Public</label><br>'.
				 '<label><input name="jp_config[privacyl]" type="radio" value="private"'. checked($this->options['privacyl'], 'private', false) .'> Private</label><br>'.
				 '<label><input name="jp_config[privacyl]" type="radio" value="friends"'. checked($this->options['privacyl'], 'friends', false) .'> Friends only</label><br>'.
				 '<label><input name="jp_config[privacyl]" type="radio" value="noxp"'. checked($this->options['privacyl'], 'noxp', false) .'> Do not crosspost</label>';
  }
  
  public function jp_comments(){
    echo '<label><input name="jp_config[comments]" type="radio" value="0"'. checked($this->options['comments'], 0, false) .'> Require users to comment on the original entry.</label><br>'.
				 '<label><input name="jp_config[comments]" type="radio" value="1"'. checked($this->options['comments'], 1, false) .'> Allow comments on all mirrored entries.</label><br>'.
				 '<label><input name="jp_config[comments]" type="radio" value="2"'. checked($this->options['comments'], 2, false) .'> Allow comments on locked mirrored entries only.</label>';
  }
  
  public function jp_cat(){
    echo '<label><input name="jp_config[cat]" type="checkbox" value="1"'. checked($this->options['cat'], 1, false) .'> Tag mirrored entries with WordPress categories.</label>';
  }
  
  public function jp_tag(){
    echo '<label><input name="jp_config[tag]" type="checkbox" value="1"'. checked($this->options['tag'], 1, false) .'> Tag mirrored entries with WordPress tags.</label>';
  }
  
  public function jp_more(){
    echo '<label><input name="jp_config[more]" type="radio" value="link"'. checked($this->options['more'], 'link', false) .'> Link back to this blog.</label><br>'.
				 '<label><input name="jp_config[more]" type="radio" value="lj-cut"'. checked($this->options['more'], 'lj-cut', false) .'> Use an lj-cut.</label><br>'.
				 '<label><input name="jp_config[more]" type="radio" value="copy"'. checked($this->options['more'], 'copy', false) .'> Mirror the entire entry with no cuts.</label>';
  }


  // validation
  function jp_settings_validate($input) {
  	//$options = (array) get_option('jp_config');
  	
  	/*if ( $some_condition == $input['field_1_1'] ) {
  		$output['field_1_1'] = $input['field_1_1'];
  	} else {
  		add_settings_error( 'my-plugin-settings', 'invalid-field_1_1', 'You have entered an invalid value into Field One.' );
  	}*/
  
    // depcrecated option
    //$output['custom_name'] = sanitize_text_field($input['custom_name']);
    
    // TODO: should probably do *something* with this, but the default sanitizers are too strict so...
    $output['custom_header'] = $input['custom_header'];
    
    if($input['header_loc'] == 0 || $input['header_loc'] == 1 ) { $output['header_loc'] = $input['header_loc']; }
    else { $output['header_loc'] = 1; }
    
    if($input['privacy'] === 'public' || $input['privacy'] === 'private' || $input['privacy'] === 'friends' ) { $output['privacy'] = $input['privacy']; }
    else { $output['privacy'] = 'public'; }
    
    if($input['privacyp'] === 'public' || $input['privacyp'] === 'private' || $input['privacyp'] === 'friends' ) { $output['privacyp'] = $input['privacyp']; }
    else { $output['privacyp'] = 'noxp'; }
    
    if($input['privacyl'] === 'public' || $input['privacyl'] === 'private' || $input['privacyl'] === 'friends' ) { $output['privacyl'] = $input['privacyl']; }
    else { $output['privacyp'] = 'friends'; }
    
    if($input['comments'] == 0 || $input['comments'] == 1 || $input['comments'] == 2 ) { $output['comments'] = $input['comments']; }
    else { $output['comments'] = 1; }
    
    if(array_key_exists('cat', $input) && (!$input['cat'] || $input['cat'] == 1)) { $output['cat'] = $input['cat']; }
    else { $output['cat'] = 0; }
    
    if(!$input['tag'] || $input['tag'] == 1) { $output['tag'] = $input['tag']; }
    else { $output['tag'] = 1; }
    
    if($input['more'] === 'link' || $input['more'] === 'lj-cut' || $input['more'] === 'copy' ) { $output['more'] = $input['more']; }
    else { $output['more'] = 'link'; }
    
  	return $output;
  }

}
endif;