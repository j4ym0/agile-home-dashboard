<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $return = isset($_POST['return']) ? $_POST['return'] : '/home';
    $return = $return == 'logout' ? '/home' : $return;
    $return = $return == '' ? '/home' : $return;

    if ($username == ""){
            $template->assign('login_message', 'Please enter a username');
    }else{
        if ($password == ""){
            $template->assign('login_message', 'Please enter a password');
        }else{
            if ($auth->noUsers) {
                $auth->addNewUser($username, $password);
            }
            if ($auth->login($username, $password)){
                header("Location: $return");
                exit();
            }else{
                $template->assign('login_message', 'Please check the username and password');
            }
        }
    }
}

$template->assign('no_users', $auth->noUsers);
