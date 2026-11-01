<?php
/* Hands the webinar to the visitor's calendar: iPhone shows the event with «Добавить», computers open it in the calendar app.
   Works without .htaccess: the right type and encoding are set here. */
$f = __DIR__ . '/wyndham-webinar-2026-11-14.ics';
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="wyndham-webinar-2026-11-14.ics"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Content-Length: ' . filesize($f));
readfile($f);
