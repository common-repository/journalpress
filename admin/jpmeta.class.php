<?php
defined('ABSPATH') or die(':(');

if(!class_exists('jpmeta')):
class jpmeta {

  private $table;

  private $journals = false;
  private $jmeta = false;
  private $xpto = false;
  
  // constructor
  public function __construct() {
    global $wpdb;

    $this->table = $wpdb->prefix .'jp_journals';
    $this->journals = $wpdb->get_results("SELECT * FROM $this->table WHERE journalUse != 'no' ORDER BY journalUse, journalUser, journalServer", ARRAY_A);
    
    return;
  }
  
//** ADD META BOX *****************************************************//
//add_meta_box( 'postjp', 'JournalPress', 'jp_post_advanced', 'post', 'advanced', 'high' );
  public function add_meta($post){
    
    // do we have any old versions of the meta keys?
    $this->getjpmeta($post->ID);
    
    // get any previously saved xpto values
    $this->xpto = get_post_meta($post->ID, '_jp_xpto', true);
    
    // display the metabox
    add_meta_box('postjp', 'JournalPress', array($this, 'printmeta'), 'post', 'normal', 'default');
  }


//** SAVE META ********************************************************//
// all we're doing here is saving our options
  //public function save_meta($post_id){
  public function save_meta($new_status, $old_status, $post){
    //check_admin_referer('jp_postmeta'); 
    $xpto = false;

    // first of all, which journals are we using?
    if(is_array($_POST) && array_key_exists('jmirrors', $_POST) && !empty($_POST['jmirrors'])){
      foreach($_POST['jmirrors'] as $m){
        $xpto[$m] = array('use' => true, 'pic' => false);
      }
    }
    
    // next our pics
    if(is_array($_POST) && array_key_exists('jpic', $_POST) && !empty($_POST['jpic'])){
      foreach($_POST['jpic'] as $k => $v){
        if(is_array($xpto) && array_key_exists($k, $xpto)){
          $xpto[$k]['pic'] = $v;
        } elseif(is_array($xpto) && !array_key_exists($k, $xpto)){
          $xpto[$k] = array('use' => false, 'pic' => $v);
        }
      }
    }

    update_post_meta($post->ID, '_jp_xpto', $xpto);
    $this->xpto = get_post_meta($post->ID, '_jp_xpto', true);
  }

//** PRINT YE META BOXXE **********************************************//
  public function printmeta($post){
    //wp_nonce_field('jp_postmeta'); 
    if(is_array($this->journals)){
      foreach($this->journals as $j){

        $piclist = $this->getpiclist($j);

        echo '<p><label class="selectit"><input type="checkbox" name="jmirrors[]" value="'. $j['journalID'] .'"'. checked(true, $this->getmirroruse($j), false) .' /> '. $this->getmirrortext($j['journalID']) .' '. $this->getmirrorname($j) .'.</label></p>';
        if($piclist)
          { echo '<p style="margin-left: 3em;"><strong>Userpic:</strong> ', $piclist ,'</p>'; }
      }
    } else { echo 'You are not mirroring to any journals! Would you like to <a href="./options-general.php?page=jp-journals">add some</a>?'; }
  }

//** HELPERS **********************************************************//
// get journals meta
  private function getjpmeta($postID){
    if(is_array($this->journals) && $postID){
      foreach($this->journals as $j){
        $jID = $j['journalID'];
        $meta = get_post_meta($postID, '_jp_xpid_'. $jID, true);
        if($meta){
          $this->jmeta[$jID] = get_post_meta($postID, '_jp_xpid_'. $jID, true);
        }
      }
    }
  }
  
  private function hasmeta($jID){
    if(is_array($this->jmeta) && array_key_exists($jID, $this->jmeta)) { return true; }
    return false;
  }
  
  private function getmirrortext($jID){
    return $this->hasmeta($jID) ? 'Edit in ' : 'Post to ';
  }
  
  private function getmirrorurl($j){
    $jID = $j['journalID'];
    if($this->hasmeta($jID))
      { return $this->jmeta[$jID]['url']; }
    elseif(!empty($j['journalComm']))
      { return 'https://'. str_replace('www.', $j['journalComm'] .'.', $j['journalServer']); }
    else
     { return 'https://'. str_replace('www.', $j['journalUser'] .'.', $j['journalServer']); }
  }
  
  private function getmirrorname($j){
    $jurl = $this->getmirrorurl($j);
    if(!empty($j['journalComm'])){
      return '<a href="'. $jurl .'" title="View Community">'. str_replace('www.', $j['journalComm'] .'.', $j['journalServer']) .'</a> as <a href="http://'. $j['journalServer'] .'/users/'. $j['journalUser'] .'" title="View Journal">'. $j['journalUser'] .'</a>';
    } else {
      return '<a href="'. $jurl .'" title="View Journal">'. str_replace('www.', $j['journalUser'] .'.', $j['journalServer']) .'</a>';
    }
  }
  
  // first check actual crossposts, then saved xpto data, then the defaults
  private function getmirroruse($j){ 
    $jID = $j['journalID'];
    if($this->hasmeta($jID))
      { return true; }
    elseif(is_array($this->xpto) && array_key_exists($jID, $this->xpto) && array_key_exists('use', $this->xpto[$jID]))
      { return $this->xpto[$jID]['use']; }
    elseif($j['journalUse'] == 'yes')
      { return true; }
    else
      { return false; }
  }

  private function getpiclist($j){
    $parr = explode("\n", $j['journalPics']);
    $str  = "<select name=\"jpic[". $j['journalID'] ."]\" id=\"jpic". $j['journalID'] ."\" class=\"inline\" style=\"height: auto; border-radius: 3px;\">\n";
    foreach($parr as $v){
      $sel = trim($v) == trim($this->getcurrentpic($j)) && (!empty($v) && !empty($this->getcurrentpic($j))) ? ' selected="selected"' : '';
      $str .= "  <option". $sel .">". esc_attr($v) ."</option>\n";
    }
    $str .= "</select>\n\n";
    
    return $str;
  }
  
  private function getcurrentpic($j){
    $jID = $j['journalID'];
    if($this->hasmeta($jID) && array_key_exists('jpic', $this->jmeta[$jID]))
      { return $this->jmeta[$jID]['jpic']; }
    elseif(is_array($this->xpto) && array_key_exists($jID, $this->xpto) && array_key_exists('pic', $this->xpto[$jID]))
      { return $this->xpto[$jID]['pic']; }
    else
      { return $j['journalPicDefault']; }
  }
  
//** FIN. *************************************************************//
}
endif;
