<?php

  namespace SimpleLdap; // {


  require_once("base.php");
  require_once("attribute_set.php");
  require_once("iterators.php");


  /* Connection
   *
   * wraps all the ldap primitives
   * connection options :
   * "bind"      => array( "dn" => string, "pw" => string, "authzid" => string, "mech" => string, "realm" => string )
   *
   */
  class Connection  {
    
    // server address
    protected $url_;
    // connection handle
    protected $handle_;
    
    
    const TRANSPORT_PLAIN  = 0;
    const TRANSPORT_TLS    = 1;
    
    const BIND_SIMPLE      = 0;
    const BIND_SASL        = 1;

    
    protected $version_;
    // transport type  : 
    protected $transport_;
    // bind type : 
    protected $type_;
    // accept redirect?
    protected $redirect_;
    // bind data
    protected $bind_;

    function __construct ($url, array $options){

      $this->url_       = $url;
      $this->handle_    = false;
      $this->version_   = @($options["version"] || 3); // default to version 3
      $this->redirect_  = @($options["redirect"]);
      $this->transport_ = @($options["transport"]);
      $this->type_      = @($options["type"]);
      $this->bind_      = @($options["bind"]);

      $this->connect($options);

    }

    function __destruct (){
      $this->disconnect();
    }

    /* connect:
     * setup the connection with server 
     */
    protected function connect (){
      if (!$this->handle_)
	$this->handle_ = @ldap_connect($this->url_);
    
      if ($this->connected()){

	// ldap version
	$this->setOption(LDAP_OPT_PROTOCOL_VERSION, $this->version_);

	// connection type
	switch ($this->transport_){
	case self::TRANSPORT_TLS:
	  $this->startTLS();
	  break;
	default:
	}

	switch ($this->type_){
	case self::BIND_SASL:
	  $this->saslBind($this->bind_["dn"], $this->bind_["authzpw"], $this->bind_["mech"], "", $this->bind_["authzid"], $this->bind_["realm"]);
	  break;
	default:
	  $this->bind($this->bind_["dn"], $this->bind_["pw"]);
	}
	
      }
      else throw new Exception(__METHOD__." failed", $this->handle_);
    }

    /* disconnect:
     * close connection
    */
    protected function disconnect (){
      if ($this->connected()) {
	@ldap_close($this->handle_);
	$this->handle_ = false;
      }
      
    }

    /* connected:
     * check if the connection is still valid
     */
    public function connected (){
      return is_resource($this->handle ());
    }
    
    /* handle:
     * returns the connection handle
     */
    protected function handle (){
      return $this->handle_;
    }

    /* guard:
     * checks if the connection is alive, and throw 
     * an exception if not
     */
    protected function guard (){
      if (!$this->connected())
	throw new Exception("disconnected from server", $this->handle_);
    }

    /* setOption:
     * wraps ldap_set_option
     */
    public function setOption ($option, $value){
      $this->guard();
      @ldap_set_option($option, $value);
    }

    protected function startTLS (){
      $this->guard();
      return @ldap_start_tls($this->handle_);
    }

    protected function saslBind($dn, $password, $mech, $dummy, $authid) {
      $this->guard();
      return @ldap_sasl_bind($this->handle_, $dn, $password, $mech, $dummy, $authid);
    }


    /* bind: 
       bind to a specific credential 
    */
    public function bind ($bdn, $pass){
      $this->guard();
      @ldap_bind($this->handle_,$bdn, $pass);
    }

    /* unbind: 
       unbind...
    */
    public function unbind (){
      $this->guard();
      @ldap_bind($this->handle_);
    }


    /* ---- query related methods -------------- */

    /* values:
     * wraps ldap_get_values
     */
    public function values ($qhandle){
      $this->guard();
      return @ldap_get_values($this->handle_,$qhandle);
    }

    /* attributes:
     * wraps ldap_get_attributes
     */
    public function attributes ($qhandle){
      $this->guard();
      return @ldap_get_attributes($this->handle_,$qhandle);
    }

    /* dn:
     * wraps ldap_get_dn
     */
    public function dn ($ehandle){
      $this->guard();
      return @ldap_get_dn($this->handle_, $ehandle);
    }

    /* firstEntry:
     */
    public function firstEntry ($qhandle){
      $this->guard();
      return @ldap_first_entry($this->handle_, $qhandle);
    }

    /* next_entry:
     */
    public function nextEntry ($qhandle){
      $this->guard();
      return @ldap_next_entry($this->handle_, $qhandle);
    }

    /* count_entries:
     */
    public function countEntries ($qhandle){
      $this->guard();
      return ldap_count_entries($this->handle_, $qhandle);
    }

    /* first_attribute:
     */
    public function firstAttribute ($ehandle){
      $this->guard();
      return @ldap_first_attribute($this->handle_, $ehandle);
    }

    /* next_attribute:
     */
    public function nextAttribute ($ehandle){
      $this->guard();
      return ldap_next_entry($this->handle_, $ehandle);
    }

    /* first_reference:
     */
    public function firstReference ($qhandle){
      $this->guard();
      return @ldap_first_reference($this->handle_, $qhandle);
    }

    /* next_reference:
     */
    public function nextReference ($qhandle){
      $this->guard();
      return @ldap_next_reference($this->handle_, $qhandle);
    }

    /* parse_reference:
     */
    public function parseReference ($qhandle, $someparam){
      $this->guard();
      return @ldap_parse_reference($this->handle_, $qhandle, $someparam);
    }

    /* free_result:
     */
    public function freeResult ($qhandle){
      $this->guard();
      return @ldap_free_result($this->handle_, $qhandle);
    }

    /* ----------------------------------------- */

    /* search:
     * wraps ldap_search, which performs a search with scope subtree
     */
    public function search ($base="", $query="(objectClass=*)", $attributes=array(), $dereference = LDAP_DEREF_ALWAYS ){
      $this->guard();      
      return @ldap_search($this->handle_, $base, $query, $attributes, 0, 0, 0, $dereference);
    }

    public function unsafe_search ($base="", $query="(objectClass=*)", $attributes=array(), $dereference = LDAP_DEREF_ALWAYS ){
      return @ldap_search($this->handle_, $base, $query, $attributes, 0, 0, 0, $dereference);
    }

    /* list_:
     * wraps ldap_list, which performs a search with scope one_level
     * (woops, "list" keyword)
     */
    public function list_ ($base="", $query="(objectClass=*)", $attributes=array(), $dereference = LDAP_DEREF_ALWAYS ){
      $this->guard();      
      return @ldap_list($this->handle_, $base, $query, $attributes, 0, 0, 0, $dereference);
    }

    public function unsafe_list_ ($base="", $query="(objectClass=*)", $attributes=array(), $dereference = LDAP_DEREF_ALWAYS ){
      return @ldap_list($this->handle_, $base, $query, $attributes, 0, 0, 0, $dereference);
    }

    /* read:
     * wraps ldap_read, which performs a search with scope base
     */
    public function read ($base="", $query="(objectClass=*)", $attributes=array(), $dereference = LDAP_DEREF_ALWAYS ){
      $this->guard();      
      return @ldap_read($this->handle_, $base, $query, $attributes, 0, 0, 0, $dereference);
    }

    public function unsafe_read ($base="", $query="(objectClass=*)", $attributes=array(), $dereference = LDAP_DEREF_ALWAYS ){
      return @ldap_read($this->handle_, $base, $query, $attributes, 0, 0, 0, $dereference);
    }

    /* add:
       wraps the "ldap_add" function 
    */
    public function add ($dn, $attrs){
      $this->guard();
      return @ldap_add($this->handle_, $dn, $attrs);
    }

    /* modify:
       wraps the "ldap_modify" function 
    */
    public function modify ($dn, $attrs){
      $this->guard();
      return @ldap_modify($this->handle_, $dn, $attrs);
    }

    /* delete:
       wraps the "ldap_delete" function 
    */
    public function delete ($dn){
      $this->guard();
      return @ldap_delete($this->handle_, $dn);
    }


    /* modAdd:
       wraps the "ldap_mod_add" function 
    */
    public function modAdd ($dn, $attrs){
      $this->guard();
      return @ldap_mod_add($this->handle_, $dn, $attrs);
    }

    /* modReplace:
       wraps the "ldap_mod_replace" function 
    */
    public function modReplace ($dn, $attrs){
      $this->guard();
      return @ldap_mod_replace($this->handle_, $dn, $attrs);
    }

    /* modDel:
       wraps the "ldap_mod_del" function 
    */
    public function modDel ($dn, $attrs){
      $this->guard();
      return @ldap_mod_del($this->handle_, $dn, $attrs);
    }


    /* higher level methods */

    const SCOPE_BASE = 0;
    const SCOPE_ONE  = 1;
    const SCOPE_SUB  = 2;

    /* iterator:
       wraps the "ldap_{read,list,search}" functions
       start a search query with the given base and requested attributes with scope $scope (default to one level)
       returns a search result iterator
    */
    public function getIterator ($base="", $query="(objectClass=*)", $attributes=array(), $dereference = LDAP_DEREF_ALWAYS, $scope = self::SCOPE_ONE){
      $this->guard();
      switch ($scope){
      case self::SCOPE_BASE:
	$search = @ldap_read($this->handle_, $base, $query, $attributes, 0, 0, 0, $dereference);
	break;
      case self::SCOPE_ONE:
	$search = @ldap_list($this->handle_, $base, $query, $attributes, 0, 0, 0, $dereference);
	break;
      case self::SCOPE_SUB:
      default:
	$search = @ldap_search($this->handle_, $base, $query, $attributes, 0, 0, 0, $dereference);
      }
      if ($search) {
	return new EntryIterator ($this, $search);
      }
      else return NULL;
    }

  } // Connection

// } // namespace

// ?>