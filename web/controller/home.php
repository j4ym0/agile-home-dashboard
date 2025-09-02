<?php

$template->append('relLinks', ['rel' => 'javascript', 'href' => '/template/js/plotly-3.0.1.min.js']);
$template->append('postRelLinks', ['rel' => 'javascript', 'href' => '/js/main.js']);
$template->append('postRelLinks', ['rel' => 'javascript', 'href' => '/js/dashboard.js']);


// Generate the date picker back and forth
$date       = new datetime();
if (isset($_GET['date'])){
    $url_params   = "&date=" . $_GET["date"];
    $date         = new datetime($_GET["date"]);
}
$day      = clone $date;
$back       = $date->modify('-1 day')->format('Y-m-d');
$forward    = $date->modify('+2 day')->format('Y-m-d');
// Check if $date is now
$is_now = ($day->format('Y-m-d') === (new DateTime())->format('Y-m-d'));

$template->assign('date_picker_back', $back);
$template->assign('date_picker_day', $day);
$template->assign('date_picker_forward', $forward);

// Check if API key is entered
$template->assign('is_setup', true);
if (($settings->get('api_key', '') == '' && $settings->get('account_number', '') == '') || 
        !$settings->get('is_setup', false)) {
    $template->assign('is_setup', false);
}

$template->assign('home_mini_live_data', false);
if ($is_now && $settings->get('home_mini_live_data', false)){
    $template->assign('home_mini_live_data', true);
}