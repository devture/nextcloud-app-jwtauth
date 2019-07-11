<?php
require dirname(__DIR__) . '/vendor/autoload.php';

$app = new \OCA\JwtAuth\AppInfo\Application();
$container = $app->getContainer();

/** @var \OCA\JwtAuth\Helper\LoginPageInterceptor $loginPageInterceptor */
$loginPageInterceptor = $container->query('loginPageInterceptor');

$loginPageInterceptor->intercept();
