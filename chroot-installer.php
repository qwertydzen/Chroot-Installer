#! /usr/bin/php
<?php
/**
 * Created by http://evgen.in/linux/chroot-installer.
 * User: Evgeny Palchevsky
 * Date: 12/23/11
 * Time: 4:18 PM
 */

$OPTION['os-support'] = array('centos', 'ubuntu');
$OPTION['os-type-repo'] = array('yum' => array('centos'), 'deb' => array('ubuntu'));

$OPTION['debug'] = false;
$OPTION['dry-run'] = false;
$OPTION['isRoot'] = false;
$OPTION['osAccept'] = false;
$OPTION['type-repo'] = 'deb';
$OPTION['copy-host-repo'] = false;
$OPTION['install-packages'] = false;
$OPTION['force'] = '';
$OPTION['centos-rpm'] = '';
$OPTION['chroot-dir'] = '';
$OPTION['base-dir'] = '';

//loop through our arguments and see what the user selected
for ($i = 1; $i < $_SERVER["argc"]; $i++)
{
   switch($_SERVER["argv"][$i])
   {
       case "-v":
       case "--version":
       echo "
Chroot-Installer (v0.1)
Copyright (c) 2011-2012, Evgeny Palchevsky
";
           exit;
           break;

       case "--chroot-dir":
           $OPTION['chroot-dir'] = $_SERVER["argv"][++$i];
           break;
       case "--centos-rpm":
           $OPTION['centos-rpm'] = $_SERVER["argv"][++$i];
           break;
       case "--dry-run":
           $OPTION['dry-run'] = true;
           break;
       case "--copy-host-repo":
           $OPTION['copy-host-repo'] = true;
           break;
       case "--install-packages":
           $OPTION['install-packages'] = $_SERVER["argv"][++$i];
           break;

       case "-?":
       case "-h":
       case "--help":
       default:
            helpMsg();
    }
}

if(!$OPTION['chroot-dir']){
    helpMsg();
    die();
}

checkUser();
checkOS();

$brief = <<<SUMMARY
Task Brief:
root  = {$OPTION['isRoot']}
linux = {$OPTION['isOS']}
chroot-dir = {$OPTION['chroot-dir']}
centos-rpm = {$OPTION['centos-rpm']}

install-packages = {$OPTION['install-packages']}
copy-host-repo   = {$OPTION['copy-host-repo']}
SUMMARY;
if($OPTION['dry-run']){
    $brief = PHP_EOL."DRY-RUN MODE!".PHP_EOL.$brief;
}
echo $brief.PHP_EOL;

$message   =  "Is it all right here? Are you sure to do this [y/n]";
if (strtolower(confirmation($message)) == 'y') {
    echo PHP_EOL.'OK! Let\'s try...'.PHP_EOL;
}else{
    echo "Verify all" . PHP_EOL;
    exit;
}

installCentosChroot();
//installDebianChroot();






/**
 * Check root privileges
 */
function checkUser(){
    global $OPTION;
    if ('root' == trim(`whoami`)) {
        $OPTION['isRoot'] = true;
    }else{
        echo 'You have to exec command as root, but you loged in as ' . `whoami` . PHP_EOL;
        exit;
    };
    return true;
}

/**
 * Check OS
 */
function checkOS(){
    global $OPTION;
    $issue = file_get_contents('/etc/issue');
    foreach($OPTION['os-support'] as $os) {
        if (strstr(strtolower($issue), strtolower($os)) !== false) {
            $OPTION['isOS'] = $os;
            $OPTION['acceptOS'] = true;
            break;
        }
    }
    if (!isset($OPTION['isOS'])) {
        echo "Sorry we don't know this OS. I recommend email to me@evgen.in with descriptions of your environment and system";
        exit;
    }
    return;
}


function confirmation($msg){
    echo PHP_EOL.$msg.PHP_EOL;
    flush();
    $confirmation  =  trim( fgets( STDIN ) );
    return $confirmation;
}

function installDebianChroot(){
    global $OPTION;
    $OPTION['codename'] = getCodename();
    $OPTION['kernel_bit'] = getKernelBit();

    echo 'Install debootstrap'.PHP_EOL;
    sendCmd("echo 'deb http://ubuntu.mirror.cambrium.nl/ubuntu/ ${OPTION['codename']} main universe' >> /etc/apt/sources.list");
    sendCmd("apt-get install debootstrap");
    sendCmd("debootstrap --variant=buildd --arch ${OPTION['kernel_bit']} ${OPTION['codename']} ${OPTION['chroot-dir']} http://archive.ubuntu.com/ubuntu/");

    echo "Copy files...".PHP_EOL;
    sendCmd("cp /etc/resolv.conf ${OPTION['chroot-dir']}/etc/resolv.conf");
    sendCmd("cp /etc/apt/sources.list ${OPTION['chroot-dir']}/etc/apt/sources.list");
    sendCmd("mount --bind /proc ${OPTION['chroot-dir']}/proc");
    sendCmd("mount --bind /dev ${OPTION['chroot-dir']}/dev");
//    sendCmd("mount -a");

    echo "Copy files...".PHP_EOL;
}

function getCodename(){
    global $OPTION;
    $release_info = file('/etc/lsb-release');
    foreach($release_info as $str){
        if(strstr($str, "DISTRIB_CODENAME")!==false){
            $arr = explode("=", $str);
            $codename = trim($arr[1]);
            break;
        }
    }
    if($codename=='x86_64'){
        $codename = 'amd64';
    }
    return $codename;
}

function getKernelBit(){
    $kernel_bit = trim(`uname -m`);
    return $kernel_bit;
}

function installCentosChroot(){
    global $OPTION;
    if (!file_exists($OPTION['chroot-dir'])) {
        sendCmd("mkdir -p ${OPTION['chroot-dir']}/var/lib/rpm");
    }
    sendCmd("yum update");
    sendCmd("rpm --rebuilddb --root=${OPTION['chroot-dir']}/var/lib/rpm");
    sendCmd("rpm -i --root=${OPTION['chroot-dir']} --nodeps ${OPTION['centos-rpm']}");
    sendCmd("yum --installroot=${OPTION['chroot-dir']} install -y rpm-build yum");
    sendCmd("cp ${OPTION['chroot-dir']}/etc/skel/.??* ${OPTION['chroot-dir']}/root");
    sendCmd("mount --bind /proc ${OPTION['chroot-dir']}/proc");
    sendCmd("mount --bind /dev ${OPTION['chroot-dir']}/dev");
    sendCmd("cp -r /etc/resolv.conf ${OPTION['chroot-dir']}/etc/resolv.conf");
    //move repos.d
    if (!$OPTION['copy-host-repo']
            && confirmation("Do you want copy host repositories [y/n]")=='y'
            || $OPTION['copy-host-repo'])
    {
        sendCmd("cp -r /etc/yum.repos.d/ ${OPTION['chroot-dir']}/etc/");
    }
    //additional sotfware
    if ($OPTION['install-packages']) {
        sendCmd("yum --installroot=${OPTION['chroot-dir']} install -y ${OPTION['install-packages']}");
    }
}

function sendCmd($cmd){
    global $OPTION;
    if($OPTION['dry-run']){
        echo $cmd.PHP_EOL;
    }else{
        $handle = proc_open($cmd, array(0=>STDIN,1=>STDOUT,2=>STDERR), $pipes);
        $retval = proc_close($handle);
        echo PHP_EOL;
    }
}


function helpMsg(){
    global $OPTION, $argv;
    echo <<<MSG
Chroot-Installer (v0.1)
Copyright (c) 2011-2012, Evgeny Palchevsky

This will install chroot environment for your experiments.
Chroot is jail environment to divide configurations with host OS.

Usage: ${argv[0]} --chroot-dir [DIR]

   --help, -help, -h, or -?     to get this help.
   --version                    to return the version of this file.
   --dry-run                    to test cli commands.
   --chroot-dir                 to set chroot directory.

   --centos-rpm [path,uri]     to set centos-release.rpm

This program is for testing and debugging purposes only;
it is NOT intended for production use.

Support: chroot-installer@evgen.in

MSG;
}

?>