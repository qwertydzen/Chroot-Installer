#! /usr/bin/php
<?php
/**
 * Created by http://evgen.in/linux/chroot-installer.
 * User: Yauhen Palcheuski
 * Date: 12/23/11
 * Time: 4:18 PM
 */

$OPTION['os-support'] = array('centos', 'ubuntu');

$OPTION['debug'] = false;
$OPTION['dry-run'] = false;
$OPTION['isRoot'] = false;
$OPTION['osAccept'] = false;
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
           echo  $_SERVER['argv'][0]."
Chroot-Installer (v0.1)
Copyright (c) 20011, Yauhen Palcheusky";
           exit;
           break;

       case "--debug":
           $OPTION['debug'] = true;
           break;

       case "--force":
           $OPTION['force'] = " --force";
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
            echo
<<<MSG
Chroot-Installer (v0.1)
Copyright (c) 20011, Yauhen Palcheusky

This will install chroot environment for your experiments.
Chroot is jail environment that helps your divide configurations with host OS.

Usage: ${argv[0]} --chroot-dir [DIR]

   --help, -help, -h, or -?     to get this help.
   --version                    to return the version of this file.
   --dry-run                        to fake the SQL and PHP commands.
   --chroot-dir                    to ignore SQL errors and keep on going.
   --centos-repo [path,uri]        to change base directory from ${OPTION['basedir']}.

This program is for testing and debugging purposes only;
it is NOT intended for production use.

Support: chroot-installer@evgen.in
MSG;

    }
}

checkUser();
checkOS();


$brief = "
Task Brief:
isRoot \t = \t " . (int)$OPTION['isRoot'] . "
OS \t = \t ${OPTION['isOS']}
chroot-dir \t = \t ${OPTION['chroot-dir']}
centos-rpm \t = \t ${OPTION['centos-rpm']}

install-packages \t = \t ${OPTION['install-packages']}
copy-host-repo \t = \t " . (int)$OPTION['copy-host-repo'] . "
";
if($OPTION['dry-run']){
    $brief = PHP_EOL."DRY-RUN MODE!".PHP_EOL.$brief;
}
echo $brief.PHP_EOL;

$message   =  "Is it here all right? Are you sure to do this [y/n]";
if (confirmation($message) == 'y') {
    echo PHP_EOL.'OK! Let\'s try...'.PHP_EOL;
}

installCentosChroot();



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

function installDebChroot(){
    echo 'Install debootstrap'.PHP_EOL;
    echo `echo 'deb http://ubuntu.mirror.cambrium.nl/ubuntu/ lucid main universe' >> /etc/apt/sources.list` . PHP_EOL;
    echo `apt-get install debootstrap` . PHP_EOL;
    echo "debootstrap --variant=buildd --arch i386 lucid ${OPTION['chroot-dir']} http://archive.ubuntu.com/ubuntu/";
}

function installCentosChroot(){
    global $OPTION;
    if (!file_exists($OPTION['chroot-dir'])) {
        sendCmd("mkdir -p ${OPTION['chroot-dir']}/var/lib/rpm");
    }
    sendCmd("rpm --rebuilddb --root=${OPTION['chroot-dir']}/var/lib/rpm");
    sendCmd("rpm -i --root=${OPTION['chroot-dir']} --nodeps ${OPTION['centos-rpm']}");
    sendCmd("yum --installroot=${OPTION['chroot-dir']} install -y rpm-build yum");
    sendCmd("cp ${OPTION['chroot-dir']}/etc/skel/.??* ${OPTION['chroot-dir']}/root");
    sendCmd("mount --bind /proc ${OPTION['chroot-dir']}/proc");
    sendCmd("mount --bind /dev ${OPTION['chroot-dir']}/dev");
    sendCmd("cp -f /etc/resolv.conf ${OPTION['chroot-dir']}/resolv.conf");
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
        echo `$cmd`.PHP_EOL;
    }
}


?>