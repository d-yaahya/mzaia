<?php
require __DIR__ . '/app/bootstrap.php';

session_destroy();
redirect('login.php');
