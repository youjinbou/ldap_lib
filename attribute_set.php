<?php

  namespace SimpleLdap;


  /* structure of an attribute record returned by get_attributes 
     array (
     <attrname> => <index>,
     <index>    => array ( count => <value count>, <attribute_values>),
     count      => <attribute count>
     )
     the php ldap API offers 4 kinds of changes : modify, mod_add, mod_replace, mod_del
     mod_add will add one value to the valueset of an attribute
     mod_replace will replace the whole valueset of an attribute
     mod_del will delete the whole valueset for an attribute
  */


  /* AttributeSetRaw:
   * holds a set of attribute values
   */
  class AttributeSet implements \ArrayAccess {

    protected $name_;
    protected $content_;
    protected $changes_;
    protected $states_;

    public function __construct ($name, array $content){
      $this->name_    = $name;
      $this->content_ = $content;
      $this->states_  = array();
    }

    public function offsetExists ($key){
      if (isset($this->states_[$key])) {
	switch ($this->states_[$key]){
	case 'delete':
	  return false;
	case 'replace':
	case 'add':
	  return true;
	}
      }
      return isset($this->content_[$key]);
    }

    public function offsetGet ($key){
      if (isset($this->states_[$key])){
	switch ($this->states_[$key]){
	case 'delete':
	  return NULL;
	case 'replace':
	case 'add':
	  return $this->changes_[$key];
	}
      }
      return $this->content_[$key];
    }
    
    public function offsetSet ($key, $value){
      $this->states_[$key]  = 'replace';
      $this->changes_[$key] = $value;
    }

    public function offsetUnset ($key){
      $this->states_[$key] = 'delete';
      unset($this->changes_[$key]);
    }

    public function ldif (){
      $start = false;
      $ldif  = array();
      if (empty($this->states_))
	return $ldif;
      sort($this->states_);
      foreach ($this->states_ as $k => $v){
	switch ($k){
	case 'add': 
	  $start = 1;
	  $ldif[] = "add: $k";
	  if (is_array($v))
	    $ldif[] = "$k: ".implode(',',$v);
	  else 
	    $ldif[] = "$k: $v";	  
	  break;
	case 'delete':
	  if ($start != 2){
	    $start = 2;
	    $ldif[] = '-';
	  }
	  $ldif[] = "delete: $k";
	  break;
	case 'replace':
	  if ($start != 3){
	    $ldif[] = '-';
	    $start  = 3;
	  }
	  $ldif[] = "replace: $k";
	  if (is_array($v))
	    $ldif[] = "$k: ".implode(',',$v);
	  else 
	    $ldif[] = "$k: $v";	  
	  break;
	}
      }

    }

  } // AttributeSet



// ?>