<?php

global $conf, $user, $langs, $db, $hookmanager;
define('NOLOGIN', true);
require_once dirname(__FILE__).'/../../../../master.inc.php';
require_once dirname(__FILE__).'/../../config.php';
require_once dirname(__FILE__).'/../../class/conformite.class.php';

if(empty($user->id)) {
    $user->fetch(1);
    $user->getrights();
}
$conf->global->MAIN_DISABLE_ALL_MAILS=1;

use PHPUnit\Framework\TestCase;

/**
 * Class ConformiteTest
 *
 * @backupGlobals disabled
 * @backupStaticAttributes enabled
 */
class ConformiteTest extends TestCase
{
    protected $savconf;
    protected $savuser;
    protected $savlangs;
    protected $savdb;
    protected $savhookmanager;

    /**
     * Constructor
     * We save global variables into local variables
     */
    public function __construct($name = null, array $data = [], $dataName = '') {
        parent::__construct($name, $data, $dataName);

        global $conf, $user, $langs, $db, $hookmanager;

        $this->savconf = $conf;
        $this->savuser = $user;
        $this->savlangs = $langs;
        $this->savdb = $db;
        $this->savhookmanager = $hookmanager;
    }

    public function testAdd() {
        global $conf, $user, $langs, $db, $hookmanager;
        $conf = $this->savconf;
        $user = $this->savuser;
        $langs = $this->savlangs;
        $db = $this->savdb;
        $hookmanager = $this->savhookmanager;

        $res = Conformite::add(10, 34);

        $this->assertEquals(43, $res);
    }
}
