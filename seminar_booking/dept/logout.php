<?php
require_once __DIR__ . '/../includes/config.php';
session_destroy();
redirect(BASE_URL . '/dept/login.php');
