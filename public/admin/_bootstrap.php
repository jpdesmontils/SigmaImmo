<?php
$root = dirname(dirname(__DIR__));
require_once $root . '/app/Database/bootstrap.php';
require_once $root . '/app/Middleware/CurrentUserMiddleware.php';
require_once $root . '/app/Middleware/PlanMiddleware.php';

sigma_db();
sigma_start_session();
$adminUser = sigma_require_user();
sigma_require_admin($adminUser);
