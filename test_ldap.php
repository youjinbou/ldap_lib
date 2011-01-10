<?php

require_once("simpleldap.php");

//use SimpleLdap\Connection;
//use SimpleLdap\Entry;


class LDAPConnection extends SimpleLdap\Connection {


  var $server_dnsname = 'localhost';
  var $server_port    = 389; // 636
  var $url            = '';

  function __construct(){
    $url = 'ldap://ldap.'.$this->server_dnsname.':'.$this->server_port;
    SimpleLdap\Connection::__construct($url, array());
  }
};

$c = new LDAPConnection();
$e = new SimpleLdap\Entry('dc=example,dc=org', 'ou=People', $c);

// ?>