<?php

  namespace SimpleLdap;

  /*  helper functions -------- */
    
  /* explode_dn:
   * 
   * ldap_explode_dn
   */
  function explode_dn($dn, $i=0){
    return @ldap_explode_dn($dn, $i);
  }

  /* explode_dn_ext:
   * explodes the passed dn in an array of arrays
   */
  function explode_dn_ext($dn){
    return array_map(function ($v){ return explode('=',$v);}, @ldap_explode_dn($dn, 0));
  }

  function implode_dn_ext(array $a){
    array_walk($a, function (&$v,&$i) { $v = $v.'='.$i;});
    return implode(',', $a);
  }

  /* query:
   * 
   * constructs a conjunctive search query from the element of the passed array 
   */
  function query(array $a){
    return "(&".array_map(function ($v){ return "(".$v.")";}, $a).")";
  }


  /* rdn_type:
   *
   * extract the type of $rdn
   */
  function rdn_type($rdn){
    $tokens = explode(',', $rdn);
    if ($tokens)
      return $tokens[0];
  }


  function query_of_rdn_type($type){
    return $type . '=*';
  }

  function dn_of_rdn($base, $rdn){
    if (empty($base))
      return $rdn;
    if (empty($rdn))
      return $base;
    return $rdn.','.$base;
  }

  function rdn_of_dn($base, $dn){
    if (empty($base))
      return $dn;
    $dn   = explode_dn_ext($dn);
    $base = explode_dn_ext($base);
    
    while (count($base)){
      if (shift($base) != shift($dn)) 
	throw new Exception(__FUNCTION__.': dn and base don\'t match');
    }
    return implode_dn_ext($dn);
  }

  /* clean_attribute_record:
   * clean up an attribute record of any
   */
  function clean_attribute_record(array $attrs){
    unset($attrs['count']);
    for ($i=0;$i<$attrs['count'];$i++){
      // drop the 'count' index in each attribute value
      unset($attrs[$attrs[$i]]['count']);
      // drop the numerically indexed values (attribute names)
      unset($attrs[$i]);
    }
    return $attrs;
  }

  /* Exception
   *
   */
  class Exception extends \Exception {
    
    var $code_;
    var $msg_;
    
    function __construct ($msg="", \resource $handle = NULL){

      if (is_resource($handle))
	$this->code_ = @ldap_errno($handle);
      
      if ($this->code_) {
	$this->msg_  = (isset($msg) ? $msg." : " : "" ) . @ldap_error($handle);
      } else {
	$this->msg_  = $msg;
      }
    }
  }


// ?>
