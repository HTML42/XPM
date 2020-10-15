



`connect.php`

```
<?php

//Required
define('XPM_DIR_xpm', _DIR() . '/../../XPM/');

//Optional
$XPM_config_file = null; //Default: ../xpm.json
define('XPM_FILE_config', _DIR() . '/../xpm.json');
define('XPM_DIR_library', _DIR() . '/library/');

//Do not touch
if (is_file(XPM_DIR_xpm . 'xpm.class.php')) {
    include XPM_DIR_xpm . 'xpm.class.php';
} else {
    echo 'Error: XPM not found.';
}

function _DIR() {
    return str_replace('\\', '/', __DIR__);
}
```