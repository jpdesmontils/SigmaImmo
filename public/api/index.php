<?php
require_once dirname(dirname(__DIR__)) . '/app/API/APIApplication.php';
(new APIApplication(dirname(dirname(__DIR__))))->run();
