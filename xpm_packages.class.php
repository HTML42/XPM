<?php

class XPM_Packages {

    public static $package_list = array();

    public static function load($project_config) {
        if (is_array($project_config) && isset($project_config['packages']) && is_array($project_config['packages'])) {
            $packages = $project_config['packages'];
            //
            foreach ($packages as $package_name) {
                self::fetch_recursive($package_name);
            }
            foreach (self::$package_list as $package_name) {
                if (substr($package_name, 0, 8) == 'github::') {
                    $package_name_github = 'github_' . preg_replace('/[^<\d\w]/', '_', substr($package_name, 8));
                    $package_local_path = XPM::_ensure_trailing_slash(XPM_DIR_library) . $package_name_github . '/';
                    //
                    var_dump($package_local_path);
                    if (!is_dir($package_local_path)) {
                        @mkdir($package_local_path);
                        $github_master_zip = 'https://github.com/' . $package_name . '/archive/master.zip';
                    }
                    die;
                    $package_startfilepath = $package_local_path . 'startup.php';
                } else {
                    $package_local_path = XPM::_ensure_trailing_slash(XPM_DIR_library) . $package_name . '/';
                    $package_startfilepath = $package_local_path . 'startup.php';
                }
                if (is_file($package_startfilepath)) {
                    include_once $package_startfilepath;
                }
            }
        }
    }

    public static function fetch_recursive($package_name) {
        $package_local_path = XPM::_ensure_trailing_slash(XPM_DIR_library) . $package_name . '/';
        if (!in_array($package_name, self::$package_list)) {
            array_push(self::$package_list, $package_name);
            if (is_file($package_local_path . 'requirements.json')) {
                $requirements = @json_decode(XPM::_get($package_local_path . 'requirements.json'), true);
                if (is_array($requirements) && !empty($requirements)) {
                    foreach ($requirements as $sub_package_name) {
                        self::fetch_recursive($sub_package_name);
                    }
                }
            }
        }
    }

}
