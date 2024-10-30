<?php
//= LJ XML-RPC CLASS =================================================//
class LJClient {

  // see http://www.livejournal.com/doc/server/ljp.csp.xml-rpc.protocol.html
  // for documentation on the livejournal XML-RPC interface

  // ------------------------------------------------------------
  var $clientid;
  var $protocol_version;
  var $strict_utf8;
  var $lineendings;
  var $rpc_timeout;
  var $journal_url;

  var $lj_srvr;
  var $lj_port;
  var $lj_xmlrpcuri;

  var $lj_userid;
  var $lj_md5pwd;
  var $lj_comm;
  
  var $lj_challenge;

  var $lj_logged;
  // ------------------------------------------------------------

  function __construct( $lj_userid = "", $lj_md5pwd = "", $server = "", $lj_comm = "" ){
    $this->clientid = "PHP-JournalPress/1.0";
    $this->protocol_version = 1;
    $this->strict_utf8 = array();
    $this->lineendings = "unix";
    $this->rpc_timeout = 60;

    $this->lj_srvr = $server;
    $this->lj_xmlrpcuri = "/interface/xmlrpc";

    $this->lj_logged = false;
    $this->lj_userid = $lj_userid;
    $this->lj_comm   = $lj_comm;
    $this->lj_md5pwd = $lj_md5pwd;
    
    $this->client = new WP_HTTP_IXR_CLIENT( 'https://' . $this->lj_srvr . $this->lj_xmlrpcuri );
    $this->client->debug = false; 
  }

  // ------------------------- API ------------------------------

  function login(){
    // first off, get the challenge
    if( !$this->client->query( 'LJ.XMLRPC.getchallenge' ) )
      { return array( FALSE, $this->client->getErrorMessage(), $this->client->getErrorCode() ); }
  
    // And retrieve the challenge string
    $response = $this->client->getResponse();
    $challenge = $response['challenge'];

    $lj_method = "LJ.XMLRPC.login";
    $params = array(
        "username" => $this->lj_userid,
        "auth_method" => 'challenge',
        "auth_challenge" => $challenge,
        "auth_response" => md5( $challenge . $this->lj_md5pwd ),
        "ver" => $this->protocol_version,
        "clientversion" => $this->clientid,
        "getpickws" => 1,
        "getpickwurls" => 1
      );

    $response = $this->do_the_thing( $lj_method, $params );
    if( $response ) {
      $this->loggedin = true;
      return array( TRUE, $this->client->getResponse(), 0 );
    } else {
      return array( FALSE, $this->client->getErrorMessage(), $this->client->getErrorCode()  );
    }
  }

  // post a new event
  function postevent( $jdata, $jmeta ){
    $this->lj_challenge = $this->get_challenge();

    $lj_method = "LJ.XMLRPC.postevent";
    $params = array(
        "username" => $this->lj_userid,
        "auth_method" => 'challenge',
        "auth_challenge" => $this->lj_challenge,
        "auth_response" => md5( $this->lj_challenge . $this->lj_md5pwd ),
        
        "ver" => $this->protocol_version,
        "lineendings" => $this->lineendings,
        
        "subject" => $jdata['subject'],
        "event" => $jdata['event'],
        "year" => $jdata['year'],
        "mon" => $jdata['mon'],
        "day" => $jdata['day'],
        "hour" => $jdata['hour'],
        "min" => $jdata['min'],
        "security" => $jdata['security'],
        //"allowmask" => $jdata['allowmask'],
        
        "props" => $jmeta
      );
	  if(array_key_exists('allowmask', $jdata)) { $params['allowmask'] = $jdata['allowmask']; }
    
    // are we trying to cross post this to a community?
    if( !empty( $this->lj_comm ) )
      { $params['usejournal'] = $this->lj_comm; }

    $response = $this->do_the_thing( $lj_method, $params );
    if( $response )
      { return array( TRUE, $this->client->getResponse(), 0 ); }
    else
      { return array( FALSE, $this->client->getErrorMessage(), $this->client->getErrorCode() ); }
  }

  // edit an old event
  function editevent( $jdata, $jmeta ){
    $this->lj_challenge = $this->get_challenge();

    $lj_method = "LJ.XMLRPC.editevent";
    $params = array(
        "username" => $this->lj_userid,
        "auth_method" => 'challenge',
        "auth_challenge" => $this->lj_challenge,
        "auth_response" => md5( $this->lj_challenge . $this->lj_md5pwd ),
        
        "ver" => $this->protocol_version,
        "lineendings" => $this->lineendings,
        
        "itemid" => $jdata['itemid'],
        "subject" => $jdata['subject'],
        "event" => $jdata['event'],
        "year" => $jdata['year'],
        "mon" => $jdata['mon'],
        "day" => $jdata['day'],
        "hour" => $jdata['hour'],
        "min" => $jdata['min'],
        "security" => $jdata['security'],
        //"allowmask" => $jdata['allowmask'],
        
        "props" => $jmeta
      );
	  if(array_key_exists('allowmask', $jdata)) { $params['allowmask'] = $jdata['allowmask']; }
    
    // are we trying to cross post this to a community?
    if( !empty( $this->lj_comm ) )
      { $params['usejournal'] = $this->lj_comm; }
    
    $response = $this->do_the_thing( $lj_method, $params );
    if( $response )
      { return array( TRUE, $this->client->getResponse(), 0 ); }
    else
      { return array( FALSE, $this->client->getErrorMessage(), $this->client->getErrorCode() ); }
  }

  // delete an event; this is pretty much just a stripped-down version of the above
  function deleteevent( $itemid ){
    $this->lj_challenge = $this->get_challenge();

    $lj_method = "LJ.XMLRPC.editevent";
    $params = array(
        'username' => $this->lj_userid,
        'auth_method' => 'challenge',
        'auth_challenge' => $this->lj_challenge,
        'auth_response' => md5( $this->lj_challenge . $this->lj_md5pwd ),
        
        'ver' => $this->protocol_version,
        'lineendings' => $this->lineendings,
        
        'itemid' => $itemid,
        'subject' => 'Deleted Post',
        'event' => ''
      );
    
    // are we trying to cross post this to a community?
    if( !empty( $this->lj_comm ) )
      { $params['usejournal'] = $this->lj_comm; }
    
    $response = $this->do_the_thing( $lj_method, $params );
    if( $response )
      { return array( TRUE, $this->client->getResponse(), 0 ); }
    else
      { return array( FALSE, $this->client->getErrorMessage(), $this->client->getErrorCode() ); }
  }
  
  // get data from an old event
  function getevents( $itemid = '' ){
    $this->lj_challenge = $this->get_challenge();
    
    $type = $itemid ? 'one' : 'lastn';

    $lj_method = "LJ.XMLRPC.getevents";
    $params = array(
        'username' => $this->lj_userid,
        'auth_method' => 'challenge',
        'auth_challenge' => $this->lj_challenge,
        'auth_response' => md5( $this->lj_challenge . $this->lj_md5pwd ),
        
        'ver' => $this->protocol_version,
        'lineendings' => $this->lineendings,
        
        'selecttype' => $type
      );
    
    // are we trying to get a specific post?
    if( $itemid )
      $params['itemid'] = $itemid;
    else
      $params['howmany'] = 1;

    // are we trying to cross post this to a community?
    if( !empty( $this->lj_comm ) )
      { $params['usejournal'] = $this->lj_comm; }
    
    $response = $this->do_the_thing( $lj_method, $params );
    if( $response )
      { return array( TRUE, $this->client->getResponse(), 0 ); }
    else
      { return array( FALSE, $this->client->getErrorMessage(), $this->client->getErrorCode() ); }
  }

  // ------------------------------------------------------------

  // ------------------- Internal Functions ---------------------

  function get_challenge(){
    // first off, get the challenge
    if( !$this->client->query( 'LJ.XMLRPC.getchallenge' ) )
      { return array( FALSE, $this->client->getErrorMessage(), $this->client->getErrorCode() ); }
  
    // And retrieve the challenge string
    $response = $this->client->getResponse();
    return $response['challenge'];
  }

  // this is no longer unnecessary... amazing!
  function do_the_thing( $method, $params ){ 
    // are we trying to use one of the "strict utf-8" servers?
    if( $this->isStrictUTF8() )
      { $this->encodeRecurse( $params ); }
  
    $xmlrpc_rsp = $this->client->query( $method, $params );
    
    return $xmlrpc_rsp;
  }
  
  function isStrictUTF8(){
    foreach( $this->strict_utf8 as $s )
      { if( stristr( $this->lj_srvr, $s ) ) { return true; } }
    return false;
  }
  
  function encodeRecurse( &$a ){
    foreach( $a as $k => $v ){
      if( is_array( $v ) ){
        $this->encodeRecurse( $a[$k] );
      } else
        { $a[$k] = $this->fixEncoding( $v ); }
    }
  }
  // Fixes the encoding to uf8
  function fixEncoding( $in_str ){
    //$cur_encoding = mb_detect_encoding( $in_str );
    //if( $cur_encoding == 'UTF-8' )
      //return $in_str;
    //else
      return utf8_encode( $in_str );
  } // fixEncoding 

  // ------------------------------------------------------------

}