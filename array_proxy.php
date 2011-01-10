<?php

  /* ArrayProxy
   *
   * while ArrayAccess offers an interface to get and set values
   * of an object as if it were an array, there's a slight issue
   * wrt the fact that it's not possible to return a reference
   * to an object field from within a function. For object type 
   * values, it's not really a problem, since they are always 
   * carried as reference within the code. However, for an array 
   * based field, it's not possible to modify "in place" a value 
   * like this:
   *
   * $obj['index1']['index2'] = 'value';
   *
   * a simple solution is to use a proxy object to carry through
   * the reference we want to access in this fashion.
   *
   * $obj['index1'] becomes an proxy instance encapsulating the 
   * real value to be assigned, thus allowing the syntax above.
   *
   */

  interface IArrayProxy {

    function getIndexedContent($key);

  }

  /* ArrayHolder
   * holds an array value to be accessed with proxy
   */
  class ArrayHolder implements \ArrayAccess, \IArrayProxy {

    protected $content_;

    public function __construct($content){
      $this->content_ = $content;
    }

    public function getIndexedContent($key){
      return $this->content_[$key];
    }

    public function offsetExists ($key) {
      return isset($this->content_[$key]);
    }

    public function offsetGet ($key){
      $value = $this->content_[$key];
      if (is_array($value)) 
	return new ArrayProxy($this, $key);
      return $value;
    }

    public function offsetSet ($key, $value){
      $this->content_[$key] = $value;
    }

    public function offsetUnset ($key){
      unset($this->content_[$key]);
    }

  }

  /* ArrayProxy
   * array wrapper to allow modification of nested arrays 
   * contained in an ArrayAccess 
   */
  class ArrayProxy implements \ArrayAccess, \IArrayProxy {
    protected $owner_;
    protected $index_;

    public function __construct($owner, $index){
      $this->owner_ = $owner;
      $this->index_ = $index;
    }

    public function offsetExists ($key){
      return isset($this->owner_[$this->index_][$key]);
    }

    public function offsetGet ($key){
      $value = $this->owner_[$this->index_][$key];
      if (is_array($value)) {
	return new ArrayProxy($this, $key);
      }
      return $value;
    }
    
    public function offsetSet ($key, $value){
      $data = $this->owner_->getIndexedContent[$this->index_];
      $data[$key] = $value;
      $this->owner[$this->index_] = $data;
    }

    public function offsetUnset ($key){
      $data = $this->owner_->getIndexedContent[$this->index_];
      unset($data[$key]);
      $this->owner[$this->index_] = $data;
    }
    
  }


// ?>