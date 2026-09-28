<?php
require_once __DIR__ . '/funcoes.php';
$_SESSION = [];
session_regenerate_id(true);
ir('login.php');
