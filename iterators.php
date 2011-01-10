<?php

  namespace SimpleLdap;

  require_once("base.php");
  require_once("attribute_set.php");
  require_once("connection.php");


  /* simple ldap search result attributes iterator
   * 
   */
  class AttributeIterator implements \Iterator {
    // connection object
    protected $connection_;
    // entry handle
    protected $search_;
    // current attribute handle
    protected $ptr_;

    function __construct (Connection $connection, resource $search, $dispose_handle = true){
      $this->connection_ = $connection;
      $this->search_     = $search;
      $this->dispose_    = $dispose_handle;
      $this->rewind();
    }

    function __destruct (){
      $this->connection_->freeResults($this->ptr_);
      if ($this->dispose_){
	$this->connection_->freeResults($this->search_);
      }
    }
  
    public function current (){
      if ($this->valid()){
	return $this->connection_->values($this->ptr_);
      }
      return false;
    }
  
    public function next (){
      if ($this->valid()){
	$this->ptr_   = $this->connection_->nextAttribute($this->ptr_);
      }
    }

    public function key (){
      if ($this->valid())
	return $this->connection_->dn($this->ptr_);
      return false;
    }

    public function valid (){
      return ($this->connection_->connected() && $this->search_ && $this->ptr_);
    }

    public function rewind (){
      $this->ptr_   = $this->connection_->firstAttribute($this->search_);
    }

  } // AttributeIterator


  /* simple ldap search result iterator
   * 
   * this class doesn't offer multilevel navigation
   * this class is readonly
   */
  class EntryIterator implements \Iterator, \Countable {

    // connection object
    protected $connection_;
    // first entry handle
    protected $search_;

    // current entry handle
    protected $ptr_;

    public function __construct (Connection $connection, resource $search, $dispose_handle = true){
      $this->connection_ = $connection;
      $this->search_     = $search;
      $this->dispose_    = $dispose_handle;
      $this->rewind();
    }

    public function __destruct (){
      $this->connection_->freeResults($this->ptr_);
      if ($this->dispose_)
	$this->connection_->freeResults($this->search_);
    }
  
    public function current (){
      if ($this->valid()){
	return $this->connection_->attributes($this->ptr_);
      }
      return false;
    }
    
    public function next (){
      if ($this->valid()){
	$this->ptr_   = $this->connection_->nextEntry($this->ptr_);
      }
    }

    public function key (){
      if ($this->valid())
	return $this->connection_->rdn($this->base(), $this->ptr_);
      return false;
    }

    public function valid (){
      return ($this->connection_->connected() && $this->search_ && $this->ptr_);
    }

    public function rewind (){
      $this->ptr_   = $this->connection_->firstEntry($this->search_);
    }

    public function count (){
      return $this->connection_->countEntries($this->search_);
    }


  } // EntryIterator


// ?>