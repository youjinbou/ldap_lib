<?php

  namespace SimpleLdap; // {


  require_once("base.php");
  require_once("attribute_set.php");
  require_once("connection.php");
  require_once("iterators.php");

  /* Entry
   *
   * - encapsulates an entry in the database 
   * - provides iterator and access (array style) to its attributes, 
   *   and access (field style) to its children 
   * 
   * use cases: 
   * 1) existing entry
   * $e = new Entry('dc=example,dc=org','ou=People',$c);
   * $e['ou'] = 'People';                    // setting the 'ou' attribute to 'People'
   *
   * // access to the entrylist ('uid')
   * $e->uid['foo'] = "....";                // creation by string
   * $e->uid['baz']['oc'] = 'inetOrgPerson'; // creation by
   * $e->uid['bar'] = $e;                    // creation by setting an entry $e
   *                                         // in the entry list ('uid') with the rdn 'uid=bar'
   * 2) unexistant entry
   * $e = new Entry('dc=example,dc=org','ou=People',$c, false); // raise an exception 
   * $e = new Entry('dc=example,dc=org','ou=People',$c, true);  // set the entry to be created remotely
   *
   * 
   * $e['oc']  = 'organizationalUnit';
   * $e['uid'] = 25323;
   * $e['sn']  = 'People';
   * $e->save();
   *
   */
   class Entry extends AttributeSet {

    // entry's relative distinguished name
    protected $rdn_;
    // base
    protected $base_;
    // connection
    protected $connection_;
    // if the entry exists in db
    protected $exists_;
    // has the content of the current entry changed ?
    protected $dirty_;
    // children Entry
    protected $children_;

    /* __construct:
     * $base is the base dn
     * $rdn  is the relative dn to the base
     * $connection is the connection instance used to access the database
     * $create indicates if the entry should be created if it doesn't exist
     *
     */
    public function __construct (STRING $base, STRING $rdn, Connection $connection, $create = false){
      $this->base_       = $base;
      $this->rdn_        = $rdn;
      $this->children_   = NULL;
    }

    /* __destruct:
     * saves any remaining changes
     *
     */
    public function __destruct (){
      $this->save();
    }

    /* base:
     *
     */
    function getBase (){
      return $this->base_;
    }

    /* rdn:
     * distinguished name
     */
    function getRdn (){
      return $this->rdn_;
    }
    
    /* dn:
     * distinguished name
     */
    function getDn (){
      return dn_of_rdn($this->getBase(), $this->getRdn());
    }

    /* hasChildren:
     *
     * checks if the current pointed entry has children
     */
    function hasChildren (){
      $this->fetchChildren();
      return (bool)(count($this->children_));
    }
    
    /* fetchChildren:
     *
     * retrieves all the possible rdn from children
     */
    function fetchChildren (){
      if ($this->children_ == NULL) {
	$children  = $this->connection_->getIterator($this->dn());
	$count     = $children->count();
	$children_ = array();
	for ($i = 0; $i < $count; $i++){
	  $rdns = explode_dn_ext($children->key());
	  $children_[rdn_type($rdns[0][0])]=1;
	  $children->next();
	}
	$this->children_ = array_keys($children_);
      }
    }

    /* getChildren:
     *
     * returns an EntryIterator iterating on _all_ the children
     */
    function getChildren (){
      return new EntryIterator($this->connection_, $this->connection_->list_($this->dn()));
    }

    /* save:
     *
     */
    function save (){
      if ($this->dirty_){
	if (!$this->exists_){
	  // all the attributes are new
	  $this->connection_->add($this->dn(), $this->changes_['add']);
	}
	else {
	  // add new attributes
	  if (isset($this->changes_['add']))
	    $this->connection_->modAdd($this->dn(), $this->changes_['add']);
	  // replace attributes
	  if (isset($this->changes_['modify']))
	    $this->connection_->modReplace($this->dn(), $this->changes_['modify']);
	  // delete attributes
	  if (isset($this->changes_['delete']))
	    $this->connection_->modDel($this->dn(), $this->changes_['delete']);
	}
      }
    }

    /* Children 
     *
     */

    /* __get:
     *
     * return the child entry with the given id
     */
    function __get ($key){
      return new EntryList($this->getBase(), $this->getRdn(), query_of_rdn_type($key), $this->connection_);
    }

    /* __isset:
     *
     * return if the child entry with the given id exists
     */
    function __isset ($id){
      return isset($this->children_[$id]);
    }

    /* __set:
     *
     * set $value to be a child of the current entry
     * $value has to be an Entry instance
     * TODO
     * moving a whole subtree to a new location => ldap_rename?
     * the value will be of type EntryList, thus we need to 
     * repeat the move operation for every element of the list
     * => get the rn of each element, build the new dn of the 
     * element, and use ldap_rename 
     */
    function __set ($index, $value){
      throw new Exception("not implemented");
    }
    
    /* __unset:
     *
     * unset $index child of the current entry
     * TODO
     * removing a whole subtree
     *
     */
    function __unset ($index){
      throw new Exception("not implemented");
    }

  } // Entry

  /* EntryList 
   * extends the EntryIterator defined above
   * and allow to recurse down the tree
   * limited to a single kind of children
  */
  class EntryList extends EntryIterator implements \ArrayAccess, \RecursiveIterator {

    // searched query data
    protected $base_;
    protected $rdn_;
    protected $selector_;
    // cached content
    protected $content_;
    // has the content of the current entry changed ?
    protected $dirty_;
    // children Entry
    protected $current_;
    // should we update on the spot
    protected $live_;
    // the updates we did
    protected $changes_;


    /* __construct:
     *
     * object constructor
     * like Entry:
     * - $base indicates the root of the subtree we're interested in
     * - $rdn is the relative dn to the base
     * $selector contains a regexp on the dn prefix we want to filter in from 
     * the dn children.
     * $connection is the connection object used.
     * $live_update indicates if updates on the object are done on the spot, or 
     * if we wait until the object end of life to save them.
     *
     */
    function __construct (STRING $base, STRING $rdn, STRING $selector, Connection $connection, $live_update = true){
      $this->base_     = $base;
      $this->rdn_      = $rdn;
      $this->parseSelector($selector);
      $search          = $connection->list_($this->getDn(), $this->getDnQuery());
      $this->live_     = $live_update;
      $this->changes_  = array();

      $this->clearCache();
      parent::__construct($connection, $search);
    }


    /* base:
     *
     */
    function __destruct (){
      // save changes
    }

    /* base:
     *
     */
    function getBase (){
      return $this->base_;
    }

    /* rdn:
     *
     */
    function getRdn (){
      return $this->rdn_;
    }

    /* dn:
     * distinguished name
     */
    function getDn (){
      return dn_of_rdn ($this->getBase(), $this->getRdn());
    }


    /* getSelectorType:
     *
     * extract the type of the selector $s
     */
    protected function parseSelector (STRING $s){
      $tokens = explode('=', $s);
      $this->selector_ = $tokens[0];
    }

    /* getSelector:
     *
     * return the most general selector from the selector type
     */
    public function getSelector (){
      return $this->selector_."=*";
    }

    /* getSelectorType:
     *
     * return the selector type
     */
    public function getSelectorType (){
      return $this->selector_;
    }

    protected function clearCache (){
      $this->current_  = false;
      $this->dirty_    = false;
    }
    
    /* fetchCurrent:
     * fetch current entry
     */
    protected function fetchCurrent (){
      if ($this->valid())
	$this->current_ = new Entry($this->getBase(),$this->key(), $this->connection_);
      else
	$this->current_ = false;
    }

    /* current:
     *
     */
    function current (){
      $this->fetchCurrent();
      return $this->current_;
    }

    /* next:
     *
     */
    function next (){
      // check if we need to save this entry
      if ($this->dirty_) {
	// save now?
	if ($this->live_) {
	  throw new Exception("not implemented");
	}
      }
      parent::next();
      $this->clearCache();
    }


    /*  Children access ------ */


    /* getChildRdn:
     * return the a child rdn from its id and the selector type
     */
    protected function getChildRdn ($id){
      return $this->getSelectorType().'='.$id.','.$this->getRdn();
    }

    /* offsetExists:
     *
     */
    function offsetExists ($key){
      $s = $this->connection_->read(dn_of_rdn($this->getBase(), $this->getChildRdn($key)));
      $r = $this->connection_->countEntries($s);
      $this->connection_->freeResult($s);
      return $r > 0;
      
    }

    /* offsetGet:
     *
     */
    function offsetGet ($key){
      if ($this->offsetExists($key)){
	return new Entry($this->getBase(), $this->getChildRdn($key), $this->connection_);
      }
      return NULL;
    }

    /* offsetSet:
     *
     */
    function offsetSet ($key, $value){
      throw new Exception("not implemented");
    }

    /* offsetUnset:
     *
     */
    function offsetUnset ($key){
      throw new Exception("not implemented");
    }

    /* hasChildren:
     *
     * check if the current pointed entry has children
     */
    function hasChildren (){
      $this->fetchCurrent();
      return ($this->current_->count());
    }
    
    /* getChildren:
     *
     * returns an EntryList iterating on the children of the current entry
     */
    function getChildren (){
      $this->fetchCurrent();
      return $this->current_->getChildren();
    }

  }

 // } namespace SimpleLdap


//?>