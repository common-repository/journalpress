<?php
defined('ABSPATH') or die(':(');

if(!class_exists('jpmirrors')):
class jpmirrors {

  private $options;
  private $table;
  
  private $error = false;
  //private $errorclass = 'notice-error'; // notice-error, notice-warning, notice-success, notice-info. is-dismissible
  
  private $journal = false;
  
  // constructor
  public function __construct() {
    global $wpdb;
    
    $this->options = get_option('jp_config');
    $this->table = $wpdb->prefix .'jp_journals';

    // are we dealing with a single journal?
    if(array_key_exists('jID', $_GET)){
      $this->journal = $wpdb->get_row("SELECT * FROM $this->table WHERE journalID = ". intval($_GET['jID']), ARRAY_A);
    }
    
    return;
  }


// this is still done ye dirty ole fashionde waye
  public function journals_page(){
        
    // process submitted forms
    // add new
    if(array_key_exists('new', $_POST) && is_array($_POST['new'])){
      check_admin_referer('jp_new');
      $r = $this->addmirror($_POST['new']);
    }
    
    // delete existing
    if(array_key_exists('deljournal', $_POST) && is_array($_POST['deljournal'])){
      check_admin_referer('jp_del');
      $r = $this->deletemirror($_POST['deljournal']);
    }
    
    // update password
    if(array_key_exists('jp_pword', $_POST) && !empty($_POST['jp_pword'])){
      check_admin_referer('jp_edit_'. $this->journal['journalID']);
      $r = $this->updatepword($_POST['jp_pword']);
    }

    // update everything else
    if(array_key_exists('jp_journal', $_POST) && is_array($_POST['jp_journal'])){
      check_admin_referer('jp_edit_'. $this->journal['journalID']); 
      $r = $this->updatemirror($_POST['jp_journal']);
    }
    
    // refresh userpics
    if(array_key_exists('jID', $_GET) && is_array($this->journal) && array_key_exists('getPics', $_GET) && $_GET['getPics'] == 1){
      check_admin_referer('jp_refreshpics_'.$_GET['jID']);
      $r = $this->updateuserpics();
    }
    

    echo '<div class="wrap"><h1>JournalPress mirrors</h1>';
    $this->printerror();
    
    if(array_key_exists('jID', $_GET) && is_array($this->journal)){
      $this->printeditmirror($this->journal);
    } else {
      $this->printmirrors();
      $this->printaddmirror();
    }
    
    echo '</div>';
  
  }  
  
//** DO THINGS ********************************************************//
  private function printerror(){
    if(is_array($this->error)){
      foreach($this->error as $e){
        $class = 'notice '. $e['class'];
      	printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $e['msg']);
    	}
    }
  }

  private function addmirror($m){
    global $wpdb;
    
    // sanitize input
    $m['server'] = sanitize_text_field($m['server']);
    $m['username'] = sanitize_text_field($m['username']);
    $m['community'] = ($m['community']) ?  sanitize_text_field($m['community']) : '';
    $m['use'] = (array_key_exists('use', $m) && ($m['use'] == 'yes' || $m['use'] == 'no' || $m['use'] == 'ask')) ? $m['use'] : 'ask';
    // check presence of all required values
    
    if(empty($m['server']))   { $this->error('Server name is required.'); return false; }
    if(empty($m['username'])) { $this->error('Username is required.'); return false; }
    if(empty($m['pword']))    { $this->error('Password is required.'); return false; }
    
    // not really a security thing, just an LJ thing...
    $m['pword'] = md5($m['pword']);
    
    // if we've gotten this far, try and get our userpics
    $p = $this->fetchtuserpics($m['server'], $m['username'], $m['pword']);
    
    if(is_array($p)){
      // now chuck everything in the database       
      $r = $wpdb->insert(
              $this->table,
              array(
                'journalServer'     => $m['server'],
                'journalUser'       => $m['username'],
                'journalPass'       => $m['pword'],
                'journalComm'       => $m['community'],
                'journalUse'        => $m['use'],
                'journalPics'       => $p['pics'],
                'journalPicDefault' => $p['default']
              ),
              array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
           );
       
       if($r == false) { $this->error('Could not add mirror to the database!'); }
       else { $this->error('Mirror successfully added!', 'notice-success'); }
     } else { $this->error("Could not connect to user <kbd>$m[username]</kbd> at <kbd>$m[server]</kbd>!"); }
    
  }
  
  private function deletemirror($ms){
    global $wpdb;
    
    if(is_array($ms)){
      $num = count($ms); 
      $jtxt = $num > 1 ? 'journals' : 'journal';
      
      foreach($ms as $m){
        if($wpdb->delete($this->table, array('journalID' => $m), array('%d')))
          { $this->error('Journal mirror with ID #'. $m .' deleted.', 'notice-info'); }
        else
          { $this->error('Could not delete journal mirror with ID #'. $m .'!'); }
      }
    }
  }
  
  private function updateuserpics(){
    global $wpdb;
    
    $p = $this->fetchtuserpics($this->journal['journalServer'], $this->journal['journalUser'], $this->journal['journalPass']);
    if(is_array($p)){
      $r = $wpdb->update( 
          	$this->table, 
          	array( 
          		'journalPics'       => $p['pics'],
              'journalPicDefault' => $p['default']
          	), 
          	array('journalID' => $this->journal['journalID']), 
          	array('%s','%s'), 
          	array('%d') 
          );
      if($r !== false) {
        $this->journal = $wpdb->get_row("SELECT * FROM $this->table WHERE journalID = ". intval($this->journal['journalID']), ARRAY_A); // refresh class cached data
        $this->error('Userpics successfully updated.', 'notice-success');
      }
      else { $this->error('Could not update userpic database!'); }
    } else { $this->error('Could not fetch userpics!'); }
  }
  
  private function fetchtuserpics($server, $username, $md5pwd){
    $ljc = new LJClient($username, $md5pwd, $server);
    $response = $ljc->login();

    if($response[0] === TRUE){
      $rsp = $response[1];
      $dpic = '';
      $piclist = !empty($rsp['pickws'] ) ? $this->parsepics($rsp['pickws']) : '';
      if( $piclist && !empty( $rsp['defaultpicurl'] ) )
        { $dpic = $this->defaultpic($rsp['pickws'], $rsp['pickwurls'], $rsp['defaultpicurl']); }
      
      return array('pics' => $piclist, 'default' => $dpic);
    } else { $this->error("Could not connect to user <kbd>$username</kbd> at <kbd>$server</kbd>!"); }
    
    return false;
  }
  
  private function parsepics($p){
    $str = '';
    foreach($p as $k => $v){
      if(!preg_match( '/["\']/', $v)) { $str .= "$v\n"; }
    }
    return trim($str);
  }
  
  // get the description of the default userpic, from the url
  private function defaultpic($p, $u, $d){
    foreach($p as $k => $v){
      if($u[$k] == $d && (!empty($u) && !empty($d))) { return $v; }
    }
  }

  private function updatepword($pword){
    global $wpdb;
    $ljc = new LJClient($this->journal['journalUser'], md5($pword), $this->journal['journalServer']);
    $response = $ljc->login();

    if($response[0] === TRUE){
      $r = $wpdb->update( 
              $this->table, 
              array( 
                'journalPass' => md5($pword)
              ), 
              array('journalID' => $this->journal['journalID']), 
              array('%s'), 
              array('%d') 
            );
        if($r !== false) { $this->error('Journal password successfully updated.', 'notice-success'); }
        else { $this->error('Could not update journal password!'); }
    } else { $this->error('Could not connect to user <kbd>'. $this->journal['journalUser'] .'</kbd> at <kbd>'. $this->journal['journalServer'] .'</kbd> with new password. Password has not been updated!'); }
  }
  
  private function updatemirror($m){
    global $wpdb;
    
    // sanitize input
    $m['pics'] = sanitize_textarea_field($m['pics']);
    $m['dpic'] = sanitize_text_field($m['dpic']);
    $m['use'] = (array_key_exists('use', $m) && ($m['use'] == 'yes' || $m['use'] == 'no' || $m['use'] == 'ask')) ? $m['use'] : 'ask';
    // check presence of all required values

    // now chuck everything in the database       
    $r = $wpdb->update(
            $this->table,
            array(
              'journalUse'        => $m['use'],
              'journalPics'       => $m['pics'],
              'journalPicDefault' => $m['dpic']
            ),
            array('journalID' => $this->journal['journalID']), 
            array('%s', '%s', '%s'), 
            array('%d') 
         );
       
    if($r == false) { $this->error('Could not edit mirror!'); }
    else {
      $this->journal = $wpdb->get_row("SELECT * FROM $this->table WHERE journalID = ". intval($this->journal['journalID']), ARRAY_A); // refresh class cached data
      $this->error('Mirror successfully edited!', 'notice-success');
    }
  }

//** SET THINGS *******************************************************//
// notice-error, notice-warning, notice-success, notice-info. is-dismissible
  private function error($str, $class = 'notice-error'){
      $this->error[] = array(
        'msg'   => $str,
        'class' => $class
      );
  }
  

//** SHOW THINGS ******************************************************//
  private function printmirrors(){
    global $wpdb;
    
?>
<h2>Current mirrors</h2>

<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
<?php wp_nonce_field('jp_del'); ?>
<div class="tablenav top"><div class="alignleft actions bulkactions">
  <input type="submit" value="Delete" name="deleteit" class="button-secondary delete" />
  <br class="clear">
</div></div>

<table class="wp-list-table widefat fixed striped posts">
<thead>
  <tr>
	 <td id="cb" class="manage-column column-cb check-column" scope="col"><label class="screen-reader-text" for="cb-select-all-1">Select All</label><input id="cb-select-all-1" type="checkbox"></td>
   <th class="manage-column" scope="col">User</th>
   <th class="manage-column column-primary" scope="col">Server</th>
   <th class="manage-column column-primary" scope="col">Community</th>
   <th class="manage-column column-primary" scope="col" style="text-align: center">Use?</th>
   <th class="manage-column column-primary" scope="col" style="text-align: center">Edit</th>
  </tr>
</thead>

<tbody>
  <tr id="the-list">
<?php
  // get the existing table data
  $js = $wpdb->get_results( 'SELECT `journalID`, `journalServer`, `journalUser`, `journalComm`, `journalUse` FROM `'. $this->table .'` ORDER BY journalUse, journalServer, journalUser, journalComm;' );

  foreach( $js as $js ) {
    $comtxt = empty( $js->journalComm ) ? '--' : "in <a href='http://$js->journalServer/community/$js->journalComm' title='View Community'>$js->journalComm</a>";
    echo "<tr><th scope=\"row\" class=\"check-column\"><input type=\"checkbox\" name=\"deljournal[]\" value=\"$js->journalID\" /></th>".
          "<td><i class=\"wp-menu-image dashicons-before dashicons-admin-users\"></i> <strong><a class='row-title' href='http://$js->journalServer/users/$js->journalUser' title='View Journal'>$js->journalUser</a></strong></td>".
          "<td><kbd>$js->journalServer</kbd></td>".
          "<td>$comtxt</td>".
          "<td style='text-align: center;'>$js->journalUse</td>".
          "<td style='text-align: center;'><a href='". $_SERVER['REQUEST_URI'] ."&jID=$js->journalID'>edit</a></td>\n</tr>\n";
  }
  
?>
  </tr>
</tbody>

<tfoot>
  <tr>
	 <td id="cb" class="manage-column column-cb check-column" scope="col"><label class="screen-reader-text" for="cb-select-all-1">Select All</label><input id="cb-select-all-1" type="checkbox"></td>
   <th class="manage-column" scope="col">User</th>
   <th class="manage-column column-primary" scope="col">Server</th>
   <th class="manage-column column-primary" scope="col">Community</th>
   <th class="manage-column column-primary" scope="col" style="text-align: center">Use?</th>
   <th class="manage-column column-primary" scope="col" style="text-align: center">Edit</th>
  </tr>
</tfoot>
</table>
</form>

<?php
  }
  
  private function printaddmirror(){
?>

  <h2>Add new mirror</h2>

<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
<?php wp_nonce_field('jp_new'); ?>
<table class="form-table"><tbody>
  <tr>
    <th scope="row">Server</th>
    <td><input name="new[server]" type="text" id="jp_server" value="www.dreamwidth.org" class="regular-text">
        <p class="description">e.g. <code>www.dreamwidth.org</code>, etc.</p></td>
  </tr>
  <tr>
		<th scope="row">Username</th>
		<td><input name="new[username]" type="text" id="jp_username" class="regular-text"></td>
  </tr>
  <tr>
    <th scope="row">Password/API key</th>
		<td><input name="new[pword]" type="password" id="jp_pword" class="regular-text">
      <p class="description">You may need to use a <a href="https://www.dreamwidth.org/manage/settings/?cat=mobile">generated API</a> key rather than your password.</p></td>
  </tr>
  <tr>
		<th scope="row">Post in community</th>
		<td><input name="new[community]" type="text" id="jp_community" class="regular-text">
  		  <p class="description">If you wish to crosspost to a community, enter its name here. Otherwise, you may leave this field blank.</p>
		</td>
  </tr>
  <tr>
		<th scope="row">Use by default?</th>
		<td><label><input name="new[use]" type="radio" value="yes" />Yes, mirror to this journal by default.</label><br />
				<label><input name="new[use]" type="radio" value="no" />No, hide this journal from the mirror list.</label><br />
				<label><input name="new[use]" type="radio" value="ask" />Ask me if I wish to use this journal, but do not post to it by default.</label></td>
  </tr>
</tbody></table>

<?php
  submit_button();
  }
  
  // edit a journal
  private function printeditmirror($m){
    if($m && is_array($m)){ 
?>

  <h3>Editing <kbd><?php echo $m['journalUser']; ?></kbd> @ <kbd><?php echo $m['journalServer']; ?></kbd> (<a href="./options-general.php?page=jp-journals">back</a>)</h3>
  <form method="post" action="./options-general.php?page=jp-journals&jID=<?php echo $m['journalID']; ?>">
  <?php wp_nonce_field('jp_edit_'. $m['journalID']); ?>
  <table class="form-table"><tbody>
  <tr>
    <th scope="row">Change password/API key</th>
    <td>
      <input name="jp_pword" type="password" id="jp_pword" size="40" />
      <p class="description">Leave this field blank to keep the current password/API key.</p>
    </td>
  </tr>
  <tr>
    <th scope="row">Use by default</th>
    <td>
      <label><input name="jp_journal[use]" type="radio" value="yes"<?php checked($m['journalUse'], 'yes'); ?>> Yes, mirror to this journal by default.</label><br />
      <label><input name="jp_journal[use]" type="radio" value="no"<?php checked($m['journalUse'], 'no'); ?>> No, hide this journal from the mirror list.</label><br />
      <label><input name="jp_journal[use]" type="radio" value="ask"<?php checked($m['journalUse'], 'ask'); ?>> Ask me if I wish to use this journal, but do not post to it by default.</label>
    </td>
  </tr>
  
  <!-- userpics -->
  <tr>
    <th scope="row">Userpics</th>
    <td>
      <textarea name="jp_journal[pics]" id="jp_pics" cols="40" rows="15"><?php echo esc_textarea($m['journalPics']); ?></textarea>
      <p class="description"><a href="<?php echo wp_nonce_url($_SERVER['REQUEST_URI']. '&getPics=1', 'jp_refreshpics_'.$m['journalID'] ) ; ?>">Auto refresh</a></p>
      <p class="description">One per line. Note you cannot have <kbd>'</kbd> or <kbd>"</kbd> in your userpic keyword.</p>
    </td>
  </tr>
  <tr valign="top">
    <th scope="row">Default userpic</th>
    <td>
      <input name="jp_journal[dpic]" type="text" id="jp_dpic" size="40" value="<?php echo esc_html($m['journalPicDefault']); ?>" />
    </td>
  </tr>
  </tbody></table>
  
  <?php submit_button(); ?>
  </form>

<?php
    } else { $this->error('Journal not found!'); $this->printerror(); }
  }
  
}
endif;
