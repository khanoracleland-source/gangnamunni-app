<?php
http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
exit('Legacy YoungCart payment bridge is disabled.');
