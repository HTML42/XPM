<?php

class XPM {

    public static $server = 'https://xpm.html42.de/';
    public static $project = null;
    public static $CACHE = array(
        'packageinfo' => array()
    );

    public static function init($update = true) {
        if (is_file(XPM_FILE_config)) {
            self::$project = json_decode(file_get_contents(XPM_FILE_config), true);
        }
        if (!is_dir(XPM_DIR_library)) {
            mkdir(XPM_DIR_library);
        }
        if ($update && is_array(self::$project) &&
                isset(self::$project['packages']) && is_array(self::$project['packages']) && !empty(self::$project['packages'])) {
            self::update(self::$project['packages']);
        }
    }

    public static function load() {
        $dir_xpm = self::_ensure_trailing_slash(XPM_DIR_xpm);
        include_once $dir_xpm . 'xpm_packages.class.php';
        XPM_Packages::load(self::$project);
    }

    public static function update($packages, $checked = array()) {
        if (is_array($packages) && !empty($packages)) {
            foreach ($packages as $package_name) {
                if (!in_array($package_name, $checked)) {
                    $package = self::update_package($package_name);
                    array_push($checked, $package_name);
                    $package['xpm']['requirements'] = @json_decode(self::_get(self::_ensure_trailing_slash(XPM_DIR_xpm) .
                                            $package_name . '/requirements.json'));
                    if (isset($package['xpm']) && isset($package['xpm']['requirements']) &&
                            is_array($package['xpm']['requirements']) && !empty($package['xpm']['requirements'])) {
                        self::update($package['xpm']['requirements'], $checked);
                    }
                }
            }
        }
    }

    public static function update_package($package_name) {
        $package = self::get_package_infos($package_name);
        self::update_xpm_package($package_name, $package['server_version']);
        self::update_local_package($package_name, $package['server_version']);
        return $package;
    }

    public static function update_xpm_package($package_name, $server_version = '') {
        $dir_xpm = self::_ensure_trailing_slash(XPM_DIR_xpm);
        $library_dir = $dir_xpm . $package_name . '/';
        $package_path = $dir_xpm . $package_name . '/';
        $package = self::get_package_info($package_path);
        if (!$package['exists']) {
            var_dump('XPM Package Update(1): ' . $package_name);
            self::_download_package($package_name);
        } else if ($server_version && $server_version != $package['version']) {
            var_dump('XPM Package Update(2): ' . $package_name);
            self::_download_package($package_name);
        }
    }

    public static function update_local_package($package_name, $server_version = '') {
        $is_github = substr($package_name, 0, 8) == 'github::';
        $package_local_path = self::_ensure_trailing_slash(XPM_DIR_library) . $package_name . '/';
        $package_xpm_path = self::_ensure_trailing_slash(XPM_DIR_xpm) . $package_name . '/';
        $package = self::get_package_info($package_local_path);
        if (!$package['exists'] || ($server_version && $server_version != $package['version'])) {
            if ($is_github) {
                $package_name_github = 'github_' . preg_replace('/[^<\d\w]/', '_', substr($package_name, 8));
                $package_local_path = str_replace($package_name, $package_name_github, $package_local_path);
                $package_xpm_path = str_replace($package_name, $package_name_github, $package_xpm_path);
            }
            self::_dir_rm($package_local_path);
            self::_copy($package_xpm_path, $package_local_path);
        }
    }

    public static function get_package_infos($package_name) {
        $package_local_path = self::_ensure_trailing_slash(XPM_DIR_library) . $package_name . '/';
        $package_xpm_path = self::_ensure_trailing_slash(XPM_DIR_xpm) . $package_name . '/';
        $server_version_filepath = self::$server . 'packages/' . $package_name . '/version';
        $server_version = @file_get_contents($server_version_filepath);
        return array(
            'local' => self::get_package_info($package_local_path),
            'xpm' => self::get_package_info($package_xpm_path),
            'server_version' => is_string($server_version) ? $server_version : '',
        );
    }

    public static function get_package_info($package_dir) {
        $package_dir = self::_ensure_trailing_slash($package_dir);
        if (!isset(self::$CACHE['packageinfo'][$package_dir])) {
            self::$CACHE['packageinfo'][$package_dir] = array(
                'exists' => is_dir($package_dir),
                'version' => self::_get($package_dir . 'version'),
                'requirements' => @json_decode(self::_get($package_dir . 'requirements.json'), true),
            );
        }
        return self::$CACHE['packageinfo'][$package_dir];
    }

    public static function _get($filepath) {
        return is_file($filepath) ? trim(file_get_contents($filepath)) : '';
    }

    public static function _download_package($package_name) {
        $dir_xpm = self::_ensure_trailing_slash(XPM_DIR_xpm);
        $library_dir = $dir_xpm . $package_name . '/';
        $is_github = substr($package_name, 0, 8) == 'github::';

        if ($is_github) {
            $package_name_clean = str_replace('github::', '', $package_name);
            $package_name_github = 'github_' . preg_replace('/[^<\d\w]/', '_', substr($package_name, 8));
            $github_master_zip = 'https://github.com/' . $package_name_clean . '/archive/master.zip';
            $package_zip = @file_get_contents($github_master_zip);
            $library_dir = $dir_xpm . $package_name_github . '/';
            $github_projectname = @end(explode('/', $package_name_clean));
        } else {
            $package_zip = @file_get_contents(self::$server . 'packages/' . $package_name . '/package.zip');
        }
        if (strlen(trim($package_zip)) > 1) {
            self::_dir_rm($library_dir);
            @mkdir($library_dir);
            //
            file_put_contents($dir_xpm . 'tmp.zip', $package_zip);
            //
            $zip = new X_ZipArchive();
            if ($zip->open($dir_xpm . 'tmp.zip') === true) {
                if ($is_github) {
                    $zip->extractSubdirTo($library_dir, $github_projectname . '-master/');
                } else {
                    $zip->extractTo($library_dir);
                }
                $zip->close();
            }
            //
            unlink($dir_xpm . 'tmp.zip');
        }
    }

    public static function _ensure_trailing_slash($folder_path) {
        $folder_path = trim($folder_path);
        if (substr($folder_path, -1) != '/') {
            $folder_path .= '/';
        }
        return $folder_path;
    }

    public static function _copy($src, $dst) {
        if (is_dir($src)) {
            @mkdir($dst);
            $files = scandir($src);
            foreach ($files as $file)
                if ($file != "." && $file != "..") {
                    self::_copy("$src/$file", "$dst/$file");
                }
        } else if (file_exists($src)) {
            copy($src, $dst);
        }
    }

    public static function _dir_rm($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object))
                        self::_dir_rm($dir . "/" . $object);
                    else
                        unlink($dir . "/" . $object);
                }
            }
            rmdir($dir);
        }
    }

}

//ZipArchive Extension
class X_ZipArchive extends ZipArchive {

    public function extractSubdirTo($destination, $subdir) {
        $errors = array();

        // Prepare dirs
        $destination = str_replace(array("/", "\\"), DIRECTORY_SEPARATOR, $destination);
        $subdir = str_replace(array("/", "\\"), "/", $subdir);

        if (substr($destination, mb_strlen(DIRECTORY_SEPARATOR, "UTF-8") * -1) != DIRECTORY_SEPARATOR)
            $destination .= DIRECTORY_SEPARATOR;

        if (substr($subdir, -1) != "/")
            $subdir .= "/";

        // Extract files
        for ($i = 0; $i < $this->numFiles; $i++) {
            $filename = $this->getNameIndex($i);

            if (substr($filename, 0, mb_strlen($subdir, "UTF-8")) == $subdir) {
                $relativePath = substr($filename, mb_strlen($subdir, "UTF-8"));
                $relativePath = str_replace(array("/", "\\"), DIRECTORY_SEPARATOR, $relativePath);

                if (mb_strlen($relativePath, "UTF-8") > 0) {
                    if (substr($filename, -1) == "/") {  // Directory
                        // New dir
                        if (!is_dir($destination . $relativePath))
                            if (!@mkdir($destination . $relativePath, 0755, true))
                                $errors[$i] = $filename;
                    } else {
                        if (dirname($relativePath) != ".") {
                            if (!is_dir($destination . dirname($relativePath))) {
                                // New dir (for file)
                                @mkdir($destination . dirname($relativePath), 0755, true);
                            }
                        }

                        // New file
                        if (@file_put_contents($destination . $relativePath, $this->getFromIndex($i)) === false)
                            $errors[$i] = $filename;
                    }
                }
            }
        }

        return $errors;
    }

}
