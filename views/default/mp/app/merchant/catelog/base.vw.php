<?php
include_once dirname(dirname(dirname(dirname(__FILE__)))) . '/inmp.vw.php';

$view['params']['menu'] = '/rest/mp/app';
$view['params']['angular-modules'] = "'ngRoute','ui.bootstrap','ui.xxt'";
$view['params']['layout-body'] = '/mp/app/merchant/catelog/base';
$view['params']['global_js'] = array('xxt.ui');
$view['params']['css'] = array(array('/mp/app/merchant/catelog', 'base', true));
$view['params']['js'] = array(array('/mp/app/merchant/catelog', 'base', true));