<?php
defined('ABSPATH') or die(':(');

if(!class_exists('jpposts')):
class jpposts {

  private $table;

  private $journals = false;
  private $options = false;
  private $jpmeta = false;
  private $xpto = false;
  
  private $post;
  
  // constructor
  public function __construct() {
    global $wpdb;

    $this->options = get_option('jp_config');

    $this->table = $wpdb->prefix .'jp_journals';
    $this->journals = $wpdb->get_results("SELECT * FROM $this->table WHERE journalUse != 'no' ORDER BY journalUse, journalUser, journalServer", ARRAY_A);
    
    return;
  }
  
//** DO THINGS ********************************************************//
  public function dopost($new_status, $old_status, $post){
    $this->post = $post;
    $this->xpto = get_post_meta($this->post->ID, '_jp_xpto', true);
    $this->getjpmeta($this->post->ID);

    // first of all, are we trashing something?
    if($new_status == 'trash')
      { $this->deletepost(); }
    
    
    
    // ... this is so badly documented
    //if ($old_status == 'publish') { return; }
    //if($new_status != 'publish' || $new_status != 'private') { return; }
  
    // haxx to catch scheduled posts
    // http://squirrelshaterobots.com/hacking/wordpress/catching-when-scheduled-posts-finally-publish-in-a-wordpress-plugin/
    // ... this breaks post editing, urgh
    //if( $when == 'now' and $this->post->post_modified != $this->post->post_date ) { return; }
    //if($post->post_status != 'publish' || $post->post_status != 'private') { return; }
  
  
    // alright so check to make sure it's okay for us to do this...
    // also have a 'jmeta' and a 'jpmeta' is really dumb...
    elseif($this->canxp()){
      // the stuff that is common across all journals
      $data = $this->formatpost();
      $meta = $this->formatmeta();
      
      // the additional stuff that's per-journal specific
      foreach($this->journals as $j){
        if($this->canxp($j['journalID'])){
          // set up our client
          $ljc = new LJClient($j['journalUser'], $j['journalPass'], $j['journalServer'], $j['journalComm']);
          
          $p['data'] = array_merge($data, $this->formatpostsingle($j['journalID']));
          $p['meta'] = array_merge($meta, $this->formatmetasingle($j['journalID']));

          // actually... do stuff?
          if(!$this->isnew($j['journalID'])){ 
            $r = $ljc->editevent($p['data'], $p['meta']);
            if($r[0] === true)
              { update_post_meta($this->post->ID, '_jp_xpid_'. $j['journalID'], array_merge($r[1], array('jpic' =>  $p['meta']['picture_keyword']))); }
              
          } else { 
            $r = $ljc->postevent($p['data'], $p['meta']);
            if($r[0] === true)
              { update_post_meta($this->post->ID, '_jp_xpid_'. $j['journalID'], array_merge($r[1], array('jpic' =>  $p['meta']['picture_keyword']))); }
            elseif($r[0] === false){
              // if that didn't work, as a last-ditch attempt, try and post backdated if we didn't before
              $p['meta']['opt_backdated'] = 1;
              $r = $ljc->postevent($p['data'], $p['meta']);
              if($r[0] === true)
                { update_post_meta($this->post->ID, '_jp_xpid_'. $j['journalID'], array_merge($r[1], array('jpic' =>  $p['meta']['picture_keyword']))); }
            }
          }
        } // endif canxp($jID)
      } // end foreach
    } // endif canxp()
    
    // else if we CAN'T xp something, check to make sure it's not already xp'd, and if it is, delete it
    else
      { $this->deletepost(); }
  }
  
  
  // TODO: non-destructive deletes, i.e. deletes that remove posts but preserve comments on posts if an option is set
  function deletepost(){
    // okay, if we're deleting a post, we need to look for -all- journals
    // said post was mirrored to, so...
    // get our list of journals
    
    foreach($this->journals as $j){
      $isxp = get_post_meta($this->post->ID, '_jp_xpid_'. $j['journalID']);
      if(!empty($isxp)){
        // set up our client
        $ljc = new LJClient($j['journalUser'], $j['journalPass'], $j['journalServer'], $j['journalComm']);
        $r = $ljc->deleteevent($isxp[0]['itemid']); 
        if($r[0] === TRUE)
          { delete_post_meta($this->post->ID, '_jp_xpid_'. $j['journalID']); }
      }
    }
  }
  
  // currently not called
  // TODO: fix... don't currently know how to restore the previous status of a post via this method
  function untrashpost($post_id){
    $post = get_post($post_id); 
    $this->dopost('', '', $post);
    
    return $post_id;
  }
  
//** FORMAT STUFF *****************************************************//
  private function formatpost(){

    // format text
    $the_event = $this->formatcuts();
    
    // linkback text
    $linkback = apply_filters('the_content', $this->getlinkback());

    // do we have a featured image?
    if($imgID = get_post_thumbnail_id($this->post->ID)){
      $img = wp_get_attachment_image($imgID, 'full', false, array('style' => 'max-width: 100%; margin: 0 auto; display: block;'));
      if($img){ $the_event = $img . $the_event; }
    }
		
    // either prepend or append the header to $the_event, depending on the config setting
    // remember that 0 is at the top, 1 at the bottom
    $the_event = ($this->options['header_loc'] > 0) ? $the_event . $linkback : $linkback . $the_event ;

    // get a timestamp for retrieving dates later
    $date = strtotime($this->post->post_date);
  
    // start building our data to post
    $jdata = array(
      'event'   => $the_event,
      'subject' => apply_filters('the_title', $this->post->post_title),
      'year'    => date('Y', $date),
      'mon'     => date('n', $date),
      'day'     => date('j', $date),
      'hour'    => date('G', $date),
      'min'     => date('i', $date)
    );
    
    // get security and append it
    $jdata = array_merge($jdata, $this->getsec());

    return $jdata;
  }
  
  private function formatpostsingle($jID){
    $jdata = array();
    
    if(!$this->isnew($jID))
      { $jdata['itemid'] = $this->jpmeta[$jID]['itemid']; }
    
    return $jdata;
  }
  
  private function formatmeta(){
    
    // build our props
    $jmeta = array(
      // enable or disable comments 
      'opt_nocomments'   => $this->cancomment(),
      // tells LJ to not run it's formatting (replacing \n with <br>, etc) because it's already been done by the texturization
      'opt_preformatted' => true,
      'taglist'          => stripslashes(implode(',', array_merge($this->getcats(), $this->gettags()))),
      'current_location' => $this->getcurrentlies('location'),
      'current_mood'     => $this->getcurrentlies('mood'),
      'current_music'    => $this->getcurrentlies('music'),
    );
    
    return $jmeta;
  }
  
  private function formatmetasingle($jID){
    $jmeta = array(
      // userpic!
      'picture_keyword' => $this->getuserpic($jID),
      'opt_backdated'   => $this->isbackdated($jID),
    );
    return $jmeta;
  }
  
  
//** DO STUFF *********************************************************//
// ... lol this is not how oop works
  private function getjpmeta($postID){
    if(is_array($this->journals) && $postID){
      foreach($this->journals as $j){
        $jID = $j['journalID'];
        $meta = get_post_meta($postID, '_jp_xpid_'. $jID, true); 
        if($meta) { $this->jpmeta[$jID] = $meta; }
      }
    }
  }

  // if jID is FALSE, this determines whether we should be crossposting this based on its wordpress privacy settings
  // if jID is TRUE, this is checking whether we're crossposting to this journal (and does not re-check privacy settings)
  // ... that behaviour is probably kinda dumb but idk
  private function canxp($jID = false){
		// not a post
		if($this->post->post_type != 'post')
			{ return false; }
		
    // just regular public posts
    if(!$jID && $this->post->post_status == 'publish' && post_password_required($this->post->ID) !== true)
      { return true; }
    
    // private posts
    elseif(!$jID && $this->post->post_status == 'private' && is_array($this->options) && array_key_exists('privacyp', $this->options) && $this->options['privacyp'] != 'noxp')
      { return true; }
      
    // password-protected posts
    elseif(!$jID && $this->post->post_status == 'publish' && post_password_required($this->post->ID) === true && is_array($this->options) && array_key_exists('privacyl', $this->options) && $this->options['privacyl'] != 'noxp')
      { return true; }
      
    // check on a per-journal basis
    // if we have xpto meta data, defer to that
    // if we don't, check our default options and use those
    elseif($jID
        && (($this->checkkeys($this->xpto, $jID, 'use') && $this->xpto[$jID]['use'] == true)
        || (!$this->checkkeys($this->xpto, $jID, 'use') && $this->checkdefaultuse($jID)))
      )
      { return true; }
      
    // default
    else
      { return false; }
  }
  
  private function isnew($jID){
    if($this->checkkeys($this->jpmeta, $jID, 'itemid') && $this->jpmeta[$jID]['itemid'] > 0) // if we already have an item id, we're not making a new post, we're trying to edit and old one
      { return false; }
    else
      { return true; }
  }

  private function formatcuts(){
    // check whether there's a more tag
    $more_present = (strpos($this->post->post_content, "<!--more-->") === false) ? false : true;

    // if there is a <!--more--> tag, process that
    if($more_present == true) {  
      $content = explode("<!--more-->", $this->post->post_content, 2);
      $the_event = apply_filters('the_content', $content[0]);
      switch($this->options['more']) {
      case "copy":
        $the_event .= apply_filters('the_content', $content[1]);
        break;
      case "link":
        $the_event .= sprintf('<p><a href="%s#more-%s">', get_permalink($this->post->ID), $this->post->ID) .'Read the rest of this entry &raquo;</a></p>';
        break;
      case "lj-cut":
        $the_event .= '<lj-cut text="Read the rest of this entry &amp;raquo;">'. apply_filters('the_content', $content[1]) .'</lj-cut>';
        break;
      default:
        $the_event .= apply_filters('the_content', $content[1]);
        break;
      }
    } 
          
    // if there's no more (... lol)
    else
      { $the_event = apply_filters('the_content', $this->post->post_content); }
    
    return $the_event;
  }
  
  // format the post header/footer
  private function getlinkback(){
    // insert the name of the page we're linking back to based on the options set
    $blogName = empty($this->options['custom_name']) ? get_option('blogname') : $this->options['custom_name'];
  
    $find = array(
        '[blog_name]',
        '[blog_link]',
        '[permalink]',
        '[comments_link]'
      );
    $replace = array(
        $blogName,
        get_option('home'),
        get_permalink($this->post->ID),
        get_permalink($this->post->ID).'#comments'
      );
      
    return str_replace($find, $replace, $this->options['custom_header']);
  }
  
  private function getcats(){
    $r = array();
    if($this->options['cat']){
      $cats = wp_get_post_categories($this->post->ID);
      // populate the categories
      // ... and take out any commas while we're at it (replace with ALT 0130)
      foreach($cats as $c){
        $cat = get_category($c);
        $r[] = str_replace(',', '‚', $cat->name);
      }
    }
    return $r;
  }
  
  private function gettags(){
    $r = array();
    if($this->options['tag']){
      $tags = wp_get_post_tags($this->post->ID);
      foreach($tags as $t){
        $r[] .= str_replace(',', '‚', $t->name);
      }
    }
    return $r;
  }
  
  private function getsec(){
    $pwhat = $this->options['privacy'];
    
    if($this->post->post_status == 'private')
      { $pwhat = $this->options['privacyp']; }
    
    if($this->post->post_status == 'private' && $this->getmask($p) > 0)
      { $pwhat = 'usemask'; }
      
    if(!empty($this->post->post_password))
      { $pwhat = $this->options['privacyl']; }
      
    $r = array('security' => 'private');
    switch($pwhat){
      case 'public':
        $r['security'] = 'public';
        break;
      case 'private':
        $r['security'] = 'private';
        break;
      case 'friends':
        $r['security'] = 'usemask';
        $r['allowmask'] = 1;
        break;
      case 'usemask':
        $r['security'] = 'usemask';
        $r['allowmask'] = $pmask;
        break;
      default:
        $r['security'] = 'private';
        break;
    }
    
    return $r;
  }
  
  private function cancomment(){
    $no_comment = 1;
    $sec = $this->getsec();
    
    if(($this->options['comments'] == 1) || ($this->options['comments'] == 2 && $sec['security'] != 'public'))
      { $no_comment = 0; }
    
    return $no_comment;
  }
  
  // this is a placeholder at the moment
  // TODO: integration with WP-Flock if i ever rewrite it...
  private function getmask(){
    return 0;
  }
  
  // another placeholder, since the bulk export functionality is gone
  private function isbackdated($jID){
    // if( $o['jp_bulk'] === true )
    if(!$this->isnew($jID))
      { return 1; }
    else
      { return 0; }
  }
  
  private function getuserpic($jID){
    if($this->checkkeys($this->xpto, $jID, 'pic')) {
      return $this->xpto[$jID]['pic'];
    } elseif($this->checkkeys($this->journals, $jID, 'journalPicDefault')) {
      return $this->journals[$jID]['journalPicDefault'];
    }
    return '';
  }
  
  private function getcurrentlies($type = 'mood'){
    if($v = get_post_meta($this->post->ID, $type, true ))
      { return stripslashes($v); }
    else
      { return ''; }
  }
  
  private function checkkeys($array, $key1, $key2 = false){
    if($key2 !== false && is_array($array) && array_key_exists($key1, $array) && array_key_exists($key2, $array[$key1]))
      { return true; }
    elseif(is_array($array) && array_key_exists($key1, $array))
      { return true; }
    else
      { return false; }
  }
  
  private function checkdefaultuse($jID){
    foreach($this->journals as $j){
      if($j['journalID'] == $jID && $j['journalUse'] == 'yes') { return true; }
    }
    return false;
  }
  
//** FIN. *************************************************************//
}
endif;