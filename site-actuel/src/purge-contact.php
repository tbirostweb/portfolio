<?php
// CLI seulement : aucune action depuis HTTP.
if(PHP_SAPI !== 'cli'){http_response_code(404);exit;}
require __DIR__.'/contact_lib.php';
contact_load_env();
$base=contact_state_dir(); foreach (['*.json'=>86400,'*.state'=>7200] as $pattern=>$seconds) foreach(glob($base.'/'.$pattern) ?: [] as $f) if(is_file($f) && filemtime($f)<time()-$seconds) @unlink($f);
