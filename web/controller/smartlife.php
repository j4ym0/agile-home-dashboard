<?php

$template->assign('tuya_configured', $settings->get('tuya_configured', ''));


$tuya = new Tuya($db, $settings);
$tuya->getDeviceList();