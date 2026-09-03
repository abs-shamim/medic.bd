<?php
/**
 * Doctor Directory Controller
 *
 * Logic remains unchanged. Large code is separated into focused files
 * under /doctor-directory/ for easier maintenance.
 */

require_once __DIR__ . '/includes/functions.php';

define('DP_ROOT_PATH', __DIR__);

$doctors_page_version = 'frontend-bilingual-doctors-directory-final-20260610';

require_once DP_ROOT_PATH . '/doctor-directory/bootstrap.php';
require_once DP_ROOT_PATH . '/doctor-directory/data-fetchers.php';
require_once DP_ROOT_PATH . '/doctor-directory/doctor-query.php';
require_once DP_ROOT_PATH . '/doctor-directory/availability.php';
require_once DP_ROOT_PATH . '/doctor-directory/page-state.php';
require_once DP_ROOT_PATH . '/doctor-directory/seo-articles.php';
require DP_ROOT_PATH . '/doctor-directory/view.php';
