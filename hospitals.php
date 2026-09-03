<?php
/**
 * Hospital Directory Controller
 *
 * Large hospital directory logic is split into focused files under
 * /hospital-directory/ so that this controller remains easy to maintain.
 */

require_once __DIR__ . '/includes/functions.php';

if (!defined('HP_ROOT_PATH')) {
    define('HP_ROOT_PATH', __DIR__);
}

require_once HP_ROOT_PATH . '/hospital-directory/bootstrap.php';
require_once HP_ROOT_PATH . '/hospital-directory/data-fetchers.php';
require_once HP_ROOT_PATH . '/hospital-directory/hospital-query.php';
require_once HP_ROOT_PATH . '/hospital-directory/page-state.php';
require_once HP_ROOT_PATH . '/hospital-directory/seo.php';
require HP_ROOT_PATH . '/hospital-directory/view.php';
