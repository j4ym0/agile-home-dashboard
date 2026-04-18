<?php

if ($e){
    $template->assign('server_error', $e->getMessage());
}else{
    $template->assign('server_error', 'Internal Server Error');
}