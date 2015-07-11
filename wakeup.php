<?php
/** config   ******/
$primaryDC = "ninetales.scimonshouse.net";
$ldapBaseDn = "CN=Users,DC=scimonshouse,DC=net";
$memberOf = "CN=WakeOnLanUsers,CN=Users,DC=scimonshouse,DC=net";

$domain = "scimonshouse";
$broadcast = "192.168.22.255";

$machines = array(
    "DORADO.scimonshouse.net" => "08606e553e7b",
    "ScottDesktop.scimonshouse.net" => "60a44c5f51cc",
);
/******************/

function send401()
{
    header('WWW-Authenticate: Basic realm="scimonshouse.net wakeup scripts"');
    header('HTTP/1.0 401 Unauthorized');
    exit;
}

function send403()
{
    header('HTTP/1.0 403 Forbidden');
    echo "You are not allowed to use this resource.";
    exit;
}

if(isset($_GET['reauth']))
{
    send401();
}

if (!isset($_SERVER['PHP_AUTH_USER'])) 
{
    send401();
} 

$connection = ldap_connect($primaryDC, 389);
ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
$authenticated = ldap_bind($connection, "{$domain}\\{$_SERVER['PHP_AUTH_USER']}", $_SERVER['PHP_AUTH_PW']);

if(!$authenticated)
{
    send401();
}

$userFilter  = "(&(objectClass=person)(sAMAccountName={login})(memberOf=" . ldap_escape($memberOf,"", LDAP_ESCAPE_FILTER) . "))";

$ldap_filter = str_replace("{login}", ldap_escape($_SERVER['PHP_AUTH_USER'],"", LDAP_ESCAPE_FILTER), $userFilter);
$searchResult = ldap_search($connection, $ldapBaseDn, $ldap_filter);

if($searchResult === false)
{
    throw new Exception(ldap_error($connection));   
}

$entry = ldap_first_entry($connection, $searchResult);

if($entry === false)
{
    send403();
}

$userdn = ldap_get_dn($connection, $entry);

if($userdn === false)   
{
    send403();  
}
    
//// AUTHENTICATED.

if($_SERVER['REQUEST_METHOD'] == 'POST')
{
    echo "woo! posted result for mac " . $_POST['wake'];
    
    $macAddressBinary = pack('H12', $_POST['wake']);
    $magicPacket = str_repeat(chr(0xff), 6).str_repeat($macAddressBinary, 16);
    if (!$fp = fsockopen('udp://{$broadcast}', 7, $errno, $errstr, 2)) {
        throw new \Exception("Cannot open UDP socket: {$errstr}", $errno);
    }
        
    fputs($fp, $magicPacket);
    
    fclose($fp);
}
    
echo "<p>Hello. Please choose a machine to wake:</p><form method='post'>";

foreach($machines as $host => $mac)
{
    echo '<button name="wake" value="' . $mac . '">' . $host . '</button>';
}

echo '</form>';




